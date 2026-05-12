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

final class PushSubscriptionController extends Controller
{
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

    public function testNotification(Request $request): JsonResponse
    {
        abort_unless($this->canSendTestNotifications($request->user()), 403);

        $users = User::whereHas('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'sent' => false,
                'message' => 'No hay usuarios con notificaciones activas. Activalas primero desde el banner.',
            ], 422);
        }

        $notifications = [
            new WebPushData(
                title: 'Alerta de Inventario: Stock Bajo',
                body: 'El producto "Cemento Selva Alegre 50kg" ha bajado de su umbral minimo.',
                severity: 'warning',
            ),
            new WebPushData(
                title: 'Discrepancia Contable Detectada',
                body: 'Una factura presenta una inconsistencia y requiere revision.',
                severity: 'critical',
            ),
            new WebPushData(
                title: 'Confirmacion de Bodega Pendiente',
                body: 'Falta confirmar una recepcion de mercaderia.',
                severity: 'info',
            ),
        ];

        $sent = 0;

        foreach ($notifications as $data) {
            foreach ($users as $user) {
                $user->notify(new GenericWebPushNotification($data));
            }

            $sent++;
            sleep(1);
        }

        return response()->json([
            'sent' => true,
            'notifications' => $sent,
            'recipients' => $users->count(),
        ]);
    }

    private function canSendTestNotifications(?User $user): bool
    {
        return $user instanceof User
            && ($user->hasGlobalBranchAccess() || $user->hasAnyRole(config('internal.notification_test_roles')));
    }
}
