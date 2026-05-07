<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logistics;

use App\Domain\Logistics\Models\FleetAlertSetting;
use App\Domain\Logistics\Services\FleetTelemetryService;
use App\Domain\Notifications\DTOs\WebPushData;
use App\Domain\Notifications\Notifications\GenericWebPushNotification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use RuntimeException;

final class FleetController extends Controller
{
    public function __construct(
        private readonly FleetTelemetryService $fleetTelemetryService,
    ) {}

    public function refresh(Request $request): JsonResponse
    {
        try {
            $dashboard = $this->fleetTelemetryService->buildDashboard();

            return response()->json([
                'success' => true,
                ...$dashboard,
            ]);
        } catch (RuntimeException $e) {
            Log::warning('FleetController::refresh unavailable', ['reason' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);
        } catch (\Throwable $e) {
            Log::error('FleetController::refresh error', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al consultar la flota GPS.',
            ], 500);
        }
    }

    public function testFleetAlerts(Request $request): JsonResponse
    {
        $users = User::whereHas('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'sent' => false,
                'message' => 'No hay dispositivos con notificaciones activas. Activalas primero desde el banner.',
            ], 422);
        }

        $notifications = [
            new WebPushData(
                title: 'GPS sin energia - MULA HINO PBW2792',
                body: 'El rastreador reporta 0 V. Revisar bateria, fusible o posible desconexion antes de despachar.',
                severity: 'critical',
            ),
            new WebPushData(
                title: 'Mantenimiento preventivo - MATRIZ NPR HBD9032',
                body: 'La unidad esta cerca del siguiente hito de kilometraje. Reservar taller y repuestos esta semana.',
                severity: 'warning',
            ),
            new WebPushData(
                title: 'Unidad subutilizada - VOLQUETA NPR PBE2233',
                body: 'No registra movimiento reciente. Confirmar si esta disponible, en taller o sin asignacion.',
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

    public function saveAlertSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', Rule::in(['global', 'vehicle'])],
            'vehicle_external_id' => ['nullable', 'string', 'max:80'],
            'vehicle_name' => ['nullable', 'string', 'max:255'],
            'stopped_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'idle_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'speed_limit_kph' => ['required', 'integer', 'min:1', 'max:160'],
            'min_voltage_12' => ['required', 'numeric', 'min:0', 'max:18'],
            'min_voltage_24' => ['required', 'numeric', 'min:0', 'max:32'],
            'stale_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'gps_signal_enabled' => ['required', 'boolean'],
            'voltage_enabled' => ['required', 'boolean'],
            'route_stop_enabled' => ['required', 'boolean'],
        ]);

        $scope = (string) $validated['scope'];
        $vehicleExternalId = $scope === 'vehicle' ? trim((string) ($validated['vehicle_external_id'] ?? '')) : '';

        if ($scope === 'vehicle' && $vehicleExternalId === '') {
            return response()->json([
                'saved' => false,
                'message' => 'Selecciona un vehiculo valido para guardar alertas especificas.',
            ], 422);
        }

        $branchId = (int) Context::get('branch_id');

        FleetAlertSetting::query()->updateOrCreate(
            [
                'branch_id' => $branchId,
                'scope' => $scope,
                'vehicle_external_id' => $vehicleExternalId,
            ],
            [
                'vehicle_name' => $scope === 'vehicle' ? ($validated['vehicle_name'] ?? null) : null,
                'stopped_minutes' => $validated['stopped_minutes'],
                'idle_minutes' => $validated['idle_minutes'],
                'speed_limit_kph' => $validated['speed_limit_kph'],
                'min_voltage_12' => $validated['min_voltage_12'],
                'min_voltage_24' => $validated['min_voltage_24'],
                'stale_minutes' => $validated['stale_minutes'],
                'gps_signal_enabled' => $validated['gps_signal_enabled'],
                'voltage_enabled' => $validated['voltage_enabled'],
                'route_stop_enabled' => $validated['route_stop_enabled'],
            ],
        );

        return response()->json([
            'saved' => true,
            'message' => $scope === 'global'
                ? 'Configuracion global guardada.'
                : 'Configuracion del vehiculo guardada.',
        ]);
    }
}
