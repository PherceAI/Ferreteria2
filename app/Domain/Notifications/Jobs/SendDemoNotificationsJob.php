<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\DTOs\WebPushData;
use App\Domain\Notifications\Notifications\GenericWebPushNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

final class SendDemoNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Enviar a TODOS los usuarios que tienen notificaciones push activas
        // (usuarios que tengan al menos una suscripción en la tabla push_subscriptions)
        $users = User::whereHas('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return;
        }

        $notifications = [
            new WebPushData(
                title: 'Alerta de Inventario: Stock Bajo',
                body: 'El producto "Cemento Selva Alegre 50kg" (Sucursal: RIO1) ha bajado de su umbral mínimo de seguridad. Quedan 15 unidades.',
                severity: 'warning',
            ),
            new WebPushData(
                title: 'Discrepancia Contable Detectada',
                body: 'La factura FAC-001-492 presenta una inconsistencia de $4.50 frente a la orden de compra. Requiere revisión manual.',
                severity: 'critical',
            ),
            new WebPushData(
                title: 'Confirmación de Bodega Pendiente',
                body: 'Falta confirmar la recepción de 20 "Tubos PVC 4 pulg". El camión llegó hace 2 horas.',
                severity: 'info',
            ),
        ];

        foreach ($notifications as $index => $data) {
            // Un pequeño retraso entre cada notificación para que no lleguen exactamente al mismo milisegundo
            // Esto es útil porque algunos sistemas operativos agrupan/sobreescriben alertas muy rápidas
            sleep(1);
            Notification::send($users, new GenericWebPushNotification($data));
        }
    }
}
