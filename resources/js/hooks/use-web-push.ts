import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

/**
 * Convierte una clave pública VAPID en base64url a Uint8Array.
 * Necesario para el argumento applicationServerKey de pushManager.subscribe().
 */
function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const output = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        output[i] = rawData.charCodeAt(i);
    }

    return output;
}

/** Convierte un ArrayBuffer a base64 para enviarlo al servidor. */
function arrayBufferToBase64(buffer: ArrayBuffer): string {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}

export type WebPushState = {
    /** El navegador soporta Web Push (Service Workers + Push API) */
    isSupported: boolean;
    /** Permiso actual: 'default' (no preguntado), 'granted', 'denied' */
    permission: NotificationPermission;
    /** El usuario tiene una suscripción activa en este dispositivo */
    isSubscribed: boolean;
    /** Hay una operación en curso (suscribir/desuscribir) */
    isLoading: boolean;
    /** Pedir permiso y suscribir al push. Retorna true si tuvo éxito. */
    subscribe: () => Promise<boolean>;
    /** Cancelar la suscripción push de este dispositivo. */
    unsubscribe: () => Promise<void>;
};

export function useWebPush(): WebPushState {
    const { vapidPublicKey } = usePage().props;

    const isSupported =
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window;

    const [permission, setPermission] = useState<NotificationPermission>(
        isSupported ? Notification.permission : 'denied',
    );
    const [isSubscribed, setIsSubscribed] = useState(false);
    const [isLoading, setIsLoading] = useState(false);

    // Verificar si ya hay una suscripción activa en este dispositivo
    useEffect(() => {
        if (!isSupported || !vapidPublicKey) {
            return;
        }

        navigator.serviceWorker.ready
            .then((registration) => registration.pushManager.getSubscription())
            .then((subscription) => setIsSubscribed(subscription !== null))
            .catch(() => setIsSubscribed(false));
    }, [isSupported, vapidPublicKey]);

    const subscribe = useCallback(async (): Promise<boolean> => {
        if (!isSupported || !vapidPublicKey) {
            return false;
        }

        setIsLoading(true);

        try {
            // 1. Pedir permiso al navegador
            const result = await Notification.requestPermission();
            setPermission(result);

            if (result !== 'granted') {
                return false;
            }

            // 2. Obtener el SW registrado y suscribir al push
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });

            // 3. Extraer claves para enviar al servidor
            const p256dhKey = subscription.getKey('p256dh');
            const authKey = subscription.getKey('auth');
            const contentEncoding = (
                (PushManager as { supportedContentEncodings?: string[] })
                    .supportedContentEncodings ?? ['aesgcm']
            )[0];

            // 4. Guardar suscripción en el servidor
            await new Promise<void>((resolve, reject) => {
                router.post(
                    '/push/subscriptions',
                    {
                        endpoint: subscription.endpoint,
                        key: p256dhKey ? arrayBufferToBase64(p256dhKey) : null,
                        token: authKey ? arrayBufferToBase64(authKey) : null,
                        contentEncoding,
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                        onSuccess: () => resolve(),
                        onError: () => reject(new Error('Failed to store subscription')),
                    },
                );
            });

            setIsSubscribed(true);

            return true;
        } catch {
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [isSupported, vapidPublicKey]);

    const unsubscribe = useCallback(async (): Promise<void> => {
        if (!isSupported) {
            return;
        }

        setIsLoading(true);

        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();

            if (!subscription) {
                setIsSubscribed(false);

                return;
            }

            // 1. Eliminar en el servidor
            await new Promise<void>((resolve) => {
                router.delete('/push/subscriptions', {
                    data: { endpoint: subscription.endpoint },
                    preserveState: true,
                    preserveScroll: true,
                    onFinish: () => resolve(),
                });
            });

            // 2. Desuscribir en el navegador
            await subscription.unsubscribe();
            setIsSubscribed(false);
        } finally {
            setIsLoading(false);
        }
    }, [isSupported]);

    return { isSupported, permission, isSubscribed, isLoading, subscribe, unsubscribe };
}
