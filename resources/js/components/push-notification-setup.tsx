import { Bell, BellOff, RefreshCw, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useWebPush } from '@/hooks/use-web-push';

/**
 * Banner fijo en la parte inferior de la pantalla.
 *
 * Aparece en DOS escenarios:
 *
 * 1. PRIMER USO: permission === 'default' y sin suscripción.
 *    Muestra el banner de "Activa las notificaciones".
 *
 * 2. RECONEXIÓN SILENCIOSA: permission === 'granted' pero isSubscribed === false.
 *    Esto ocurre cuando el permiso ya fue otorgado en el pasado pero la
 *    suscripción no se guardó en el servidor (ej: error de red, bug previo).
 *    El banner muestra un mensaje de reconexión para que el usuario
 *    pueda re-suscribirse sin tener que revocar y volver a dar permisos.
 *
 * Al estar fuera del flujo del documento (fixed), no afecta el layout de
 * ninguna página. Desaparece al activar, denegar o cerrar manualmente.
 */
export function PushNotificationSetup() {
    const { isSupported, permission, isSubscribed, isLoading, subscribe } =
        useWebPush();
    const [dismissed, setDismissed] = useState(false);

    // No mostrar si: no soportado, ya suscrito, o el usuario cerró el banner
    if (!isSupported || isSubscribed || dismissed) return null;

    // No mostrar si el permiso está bloqueado (el usuario lo rechazó explícitamente)
    if (permission === 'denied') return null;

    // Sí mostrar si: permission === 'default' (nunca preguntado)
    //           O si: permission === 'granted' pero sin suscripción guardada (reconexión)
    const isReconnecting = permission === 'granted' && !isSubscribed;

    const handleActivate = async () => {
        await subscribe();
    };

    return (
        <div
            role="banner"
            aria-label={isReconnecting ? 'Reconectar notificaciones' : 'Activar notificaciones push'}
            className="fixed bottom-4 left-1/2 z-50 w-full max-w-sm -translate-x-1/2 px-4 sm:px-0"
        >
            <div className={`flex items-start gap-3 rounded-xl border p-4 shadow-xl ${
                isReconnecting
                    ? 'border-amber-200 bg-white dark:border-amber-800 dark:bg-zinc-900'
                    : 'border-blue-200 bg-white dark:border-blue-800 dark:bg-zinc-900'
            }`}>
                <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${
                    isReconnecting
                        ? 'bg-amber-100 dark:bg-amber-900/40'
                        : 'bg-blue-100 dark:bg-blue-900/40'
                }`}>
                    {isReconnecting
                        ? <RefreshCw className="h-4 w-4 text-amber-600 dark:text-amber-400" />
                        : <Bell className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    }
                </div>

                <div className="flex-1 text-sm">
                    <p className="font-semibold text-zinc-900 dark:text-zinc-100">
                        {isReconnecting
                            ? 'Reconectar notificaciones'
                            : 'Activa las notificaciones'
                        }
                    </p>
                    <p className="mt-0.5 text-zinc-500 dark:text-zinc-400">
                        {isReconnecting
                            ? 'Tu permiso está activo pero este dispositivo no está registrado. Reconecta para recibir alertas.'
                            : 'Recibe alertas de stock y recepciones aunque el sistema esté cerrado.'
                        }
                    </p>
                    <div className="mt-3 flex gap-2">
                        <Button
                            size="sm"
                            onClick={handleActivate}
                            disabled={isLoading}
                            className={`h-7 px-3 text-xs text-white ${
                                isReconnecting
                                    ? 'bg-amber-500 hover:bg-amber-600'
                                    : 'bg-blue-600 hover:bg-blue-700'
                            }`}
                        >
                            {isLoading
                                ? 'Conectando…'
                                : isReconnecting ? 'Reconectar' : 'Activar'
                            }
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setDismissed(true)}
                            className="h-7 px-3 text-xs text-zinc-500 hover:text-zinc-700"
                        >
                            Ahora no
                        </Button>
                    </div>
                </div>

                <button
                    onClick={() => setDismissed(true)}
                    className="shrink-0 rounded-md p-0.5 text-zinc-400 transition-colors hover:text-zinc-600 dark:hover:text-zinc-200"
                    aria-label="Cerrar"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    );
}

/**
 * Toggle para la página de Configuración > Seguridad.
 * Permite activar/desactivar notificaciones después del primer setup.
 */
export function PushNotificationToggle() {
    const {
        isSupported,
        permission,
        isSubscribed,
        isLoading,
        subscribe,
        unsubscribe,
    } = useWebPush();

    if (!isSupported) {
        return (
            <p className="text-sm text-zinc-500">
                Tu navegador no soporta notificaciones push.
            </p>
        );
    }

    if (permission === 'denied') {
        return (
            <div className="flex items-center gap-2 text-sm text-amber-600 dark:text-amber-400">
                <BellOff className="h-4 w-4 shrink-0" />
                <span>
                    Notificaciones bloqueadas. Actívalas en la configuración de
                    tu navegador y recarga la página.
                </span>
            </div>
        );
    }

    // permission === 'granted' pero sin suscripción → botón de reconexión explícito
    const label = isSubscribed ? 'Desactivar' : isLoading ? '…' : 'Activar';

    return (
        <div className="flex items-center justify-between gap-4">
            <div>
                <p className="text-sm font-medium">Notificaciones push</p>
                <p className="text-xs text-zinc-500">
                    {isSubscribed
                        ? 'Recibes alertas en este dispositivo aunque el sistema esté cerrado.'
                        : permission === 'granted'
                            ? 'Permiso concedido pero sin suscripción activa en este dispositivo. Haz clic en Activar.'
                            : 'Actívalas para recibir alertas de stock, caducidad y recepciones.'}
                </p>
            </div>
            <Button
                size="sm"
                variant={isSubscribed ? 'outline' : 'default'}
                onClick={isSubscribed ? unsubscribe : subscribe}
                disabled={isLoading}
                className="shrink-0"
            >
                {label}
            </Button>
        </div>
    );
}
