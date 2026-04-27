<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Domain\Notifications\DTOs\WebPushData;
use App\Domain\Notifications\Notifications\GenericWebPushNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notifications\StorePushSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Gestiona las suscripciones de web push del usuario autenticado.
 *
 * No necesita branch scope: una suscripción es por dispositivo del usuario,
 * no por sucursal. El usuario recibe notificaciones de todas sus sucursales
 * en el mismo dispositivo.
 *
 * No necesita auditoría (Auditable): no es un dato operativo de pherce_intel.
 */
final class PushSubscriptionController extends Controller
{
    /**
     * Guarda o actualiza la suscripción push del dispositivo actual.
     * El browser llama a este endpoint tras obtener permiso del usuario.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->updatePushSubscription(
            endpoint: $request->string('endpoint')->toString(),
            key: $request->string('key')->toString() ?: null,
            token: $request->string('token')->toString() ?: null,
            contentEncoding: $request->string('contentEncoding')->toString() ?: null,
        );

        return response()->json(['subscribed' => true], 201);
    }

    /**
     * Elimina la suscripción push del dispositivo actual.
     * Se llama cuando el usuario desactiva las notificaciones.
     */
    public function destroy(Request $request): Response
    {
        $endpoint = $request->string('endpoint')->toString();

        if ($endpoint !== '') {
            /** @var User $user */
            $user = $request->user();
            $user->deletePushSubscription($endpoint);
        }

        return response()->noContent();
    }

    /**
     * Envía una secuencia de 3 notificaciones de demostración de forma síncrona
     * a todos los usuarios con suscripciones push activas.
     *
     * Síncrono a propósito: no requiere queue worker activo para funcionar
     * en desarrollo y demos. En producción, las alertas reales irán por queue.
     */
    public function testNotification(Request $request): JsonResponse
    {
        // Todos los usuarios con al menos una suscripción push registrada
        $users = User::whereHas('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'sent' => false,
                'message' => 'No hay usuarios con notificaciones activas. Actívalas primero desde el banner.',
            ], 422);
        }

        $notifications = [
            new WebPushData(
                title: '⚠️ Alerta de Inventario: Stock Bajo',
                body: 'El producto "Cemento Selva Alegre 50kg" (Sucursal: RIO1) ha bajado de su umbral mínimo. Quedan 15 unidades.',
                severity: 'warning',
            ),
            new WebPushData(
                title: '🔴 Discrepancia Contable Detectada',
                body: 'La factura FAC-001-492 presenta una inconsistencia de $4.50 frente a la orden de compra. Requiere revisión.',
                severity: 'critical',
            ),
            new WebPushData(
                title: '📦 Confirmación de Bodega Pendiente',
                body: 'Falta confirmar la recepción de 20 "Tubos PVC 4 pulg". El camión llegó hace 2 horas.',
                severity: 'info',
            ),
        ];

        $sent = 0;

        foreach ($notifications as $data) {
            foreach ($users as $user) {
                $user->notify(new GenericWebPushNotification($data));
            }
            $sent++;
            // Pequeña pausa entre alertas para que el OS móvil no las agrupe/descarte
            sleep(1);
        }

        return response()->json([
            'sent' => true,
            'notifications' => $sent,
            'recipients' => $users->count(),
        ]);
    }
}
