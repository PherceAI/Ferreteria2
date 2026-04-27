import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

/**
 * Convierte una clave pública VAPID en base64url a Uint8Array.
 * Necesario para el argumento applicationServerKey de pushManager.subscribe().
 */
function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
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

    // On mount: check subscription state with a timeout.
    // If serviceWorker.ready hangs (no SW registered), we still show the banner after 3s.
    // If PUSH_VERSION changes, force-unsubscribe stale browser subscription.
    useEffect(() => {
        if (!isSupported || !vapidPublicKey) {
            return;
        }

        const PUSH_VERSION = 'v3';
        const storageKey = 'push_reset_version';
        let resolved = false;

        const handleReady = async () => {
            if (resolved) return;
            resolved = true;

            try {
                // Ensure SW is registered first
                await navigator.serviceWorker.register('/sw.js');
                const registration = await navigator.serviceWorker.ready;
                const subscription = await registration.pushManager.getSubscription();
                const currentVersion = localStorage.getItem(storageKey);

                if (subscription && currentVersion !== PUSH_VERSION) {
                    await subscription.unsubscribe();
                    localStorage.setItem(storageKey, PUSH_VERSION);
                    setIsSubscribed(false);
                } else if (!subscription) {
                    localStorage.setItem(storageKey, PUSH_VERSION);
                    setIsSubscribed(false);
                } else {
                    setIsSubscribed(true);
                }
            } catch {
                setIsSubscribed(false);
            }
        };

        // Timeout: if SW.ready hasn't resolved in 3s, show the banner anyway
        const timeout = setTimeout(() => {
            if (!resolved) {
                resolved = true;
                setIsSubscribed(false);
            }
        }, 3000);

        handleReady();

        return () => clearTimeout(timeout);
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
            const contentEncoding = ((
                PushManager as { supportedContentEncodings?: string[] }
            ).supportedContentEncodings ?? ['aesgcm'])[0];

            // 4. Guardar suscripción en el servidor via fetch (no Inertia router)
            // El router de Inertia espera una respuesta Inertia, no JSON plano.
            const csrfToken = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');

            const storeRes = await fetch('/push/subscriptions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': csrfToken
                        ? decodeURIComponent(csrfToken)
                        : '',
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint,
                    key: p256dhKey ? arrayBufferToBase64(p256dhKey) : null,
                    token: authKey ? arrayBufferToBase64(authKey) : null,
                    contentEncoding,
                }),
            });

            if (!storeRes.ok) {
                throw new Error('Failed to store subscription');
            }

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
            const subscription =
                await registration.pushManager.getSubscription();

            if (!subscription) {
                setIsSubscribed(false);

                return;
            }

            // 1. Eliminar en el servidor via fetch (no Inertia router)
            const csrfToken = document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');

            await fetch('/push/subscriptions', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': csrfToken
                        ? decodeURIComponent(csrfToken)
                        : '',
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });

            // 2. Desuscribir en el navegador
            await subscription.unsubscribe();
            setIsSubscribed(false);
        } finally {
            setIsLoading(false);
        }
    }, [isSupported]);

    return {
        isSupported,
        permission,
        isSubscribed,
        isLoading,
        subscribe,
        unsubscribe,
    };
}
