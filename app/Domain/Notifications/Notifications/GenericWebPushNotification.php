<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\DTOs\WebPushData;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notificación web push genérica.
 *
 * Usada por PushNotificationService para enviar notificaciones desde cualquier módulo.
 * Cada módulo futuro (Inventory, Purchasing, etc.) puede crear su propia clase
 * de notificación que use WebPushChannel directamente en su método via().
 */
final class GenericWebPushNotification extends Notification
{
    public function __construct(
        private readonly WebPushData $data,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $message = (new WebPushMessage)
            ->title($this->data->title)
            ->icon($this->data->icon)
            ->body($this->data->body)
            // Incluimos severity en el payload para que el Service Worker
            // pueda aplicar vibración y sonido diferenciados por nivel de alerta
            ->data([
                'url'      => $this->data->url,
                'severity' => $this->data->severity,
            ]);

        if ($this->data->tag !== null) {
            $message->tag($this->data->tag);
        }

        // Para alertas críticas, forzar interacción del usuario antes de cerrar
        if ($this->data->severity === 'critical') {
            $message->requireInteraction(true);
        }

        return $message;
    }
}
