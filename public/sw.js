/**
 * Service Worker — Comercial San Francisco (Panel Pherce)
 * Version: 2.0.2 — 2026-04-22
 *
 * Responsabilidades:
 *  1. Recibir eventos push y mostrar notificaciones del OS
 *  2. Manejar clicks en notificaciones (abrir/focalizar la app)
 *
 * Compatibilidad: Android Chrome, iOS Safari (16.4+), Windows Chrome/Edge
 */

'use strict';

// ─── Push: recibe el mensaje del servidor y muestra la notificación ───────────

self.addEventListener('push', (event) => {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch {
        payload = { title: 'Comercial San Francisco', body: event.data.text() };
    }

    const title = payload.title ?? 'Comercial San Francisco';

    // La librería webpush-php serializa WebPushMessage plano y deja los campos
    // custom dentro de `data`. Por eso severity vive en payload.data.severity,
    // NO en payload.severity. Antes usábamos el camino incorrecto y severity
    // siempre quedaba 'info' → silent: true → los toasts no aparecían.
    const severity = payload.data?.severity ?? payload.severity ?? 'info';
    const url = payload.data?.url ?? '/dashboard';

    // Tag único por notificación — evita que se sobreescriban entre sí
    // Usamos timestamp para que cada push aparezca por separado en pantalla bloqueada
    const uniqueTag = `csf-${severity}-${Date.now()}`;

    const options = {
        body: payload.body ?? '',

        // Ícono principal (logo de la app)
        icon: '/icons/icon-192.png',

        // Badge: ícono pequeño que aparece en la barra de status de Android
        badge: '/icons/icon-192.png',

        // Tag único = no reemplaza notificaciones anteriores
        tag: payload.tag ? `${payload.tag}-${Date.now()}` : uniqueTag,

        // renotify: true = vibra aunque el tag se repita (crítico para Chrome escritorio)
        renotify: true,

        // Los datos URL para cuando el usuario hace clic
        data: { url },

        // Vibración en móvil Android
        // Patrón: [vibra, pausa, vibra] en milisegundos
        vibrate: severity === 'critical' ? [300, 100, 300, 100, 300] : [200, 100, 200],

        // Notificaciones críticas NO se auto-cierran (requieren acción del usuario)
        requireInteraction: severity === 'critical',

        // silent SIEMPRE false: queremos que suene/vibre en todas las alertas de demo.
        // Poner silent: true en Chrome escritorio suprime el popup y solo deja el
        // toast en el centro de notificaciones — el cliente "no ve" nada.
        silent: false,

        // Timestamp legible en la notificación (Chrome desktop lo muestra)
        timestamp: Date.now(),
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ─── Click en notificación: abre/focaliza la ventana de la app ───────────────

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification.data?.url ?? '/dashboard';

    // Construir URL absoluta con el origin del SW
    const absoluteUrl = self.location.origin + targetUrl;

    event.waitUntil(
        clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Si ya hay una ventana abierta de la app, la enfocamos y navegamos
                for (const client of clientList) {
                    if ('focus' in client) {
                        client.focus();
                        if ('navigate' in client) {
                            return client.navigate(absoluteUrl);
                        }
                        return;
                    }
                }

                // Si no hay ninguna ventana abierta, abrimos una nueva
                if (clients.openWindow) {
                    return clients.openWindow(absoluteUrl);
                }
            })
    );
});

// ─── Instalación: activar inmediatamente sin esperar ventanas existentes ──────

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});
