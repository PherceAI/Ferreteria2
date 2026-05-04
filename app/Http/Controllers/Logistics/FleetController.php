<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logistics;

use App\Domain\Notifications\DTOs\WebPushData;
use App\Domain\Notifications\Notifications\GenericWebPushNotification;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proxy seguro entre el frontend y la API de rastreo GPS (Ubika Ecuador / Traccar).
 *
 * El hash de autenticación NUNCA se expone al browser — solo existe en el backend.
 * El controller consume la API externa, transforma los datos y los devuelve limpios.
 *
 * No necesita branch scope: la flota es de toda la empresa.
 * No necesita auditoría: es consulta de lectura de API externa.
 */
final class FleetController extends Controller
{
    /**
     * Obtiene el estado en tiempo real de todos los vehículos de la flota
     * haciendo proxy a la API de Ubika Ecuador.
     *
     * El frontend llama a este endpoint al presionar "Actualizar".
     */
    public function refresh(Request $request): JsonResponse
    {
        $apiUrl = config('services.ubika.devices_url');
        $apiHash = config('services.ubika.user_api_hash');

        if (! is_string($apiUrl) || $apiUrl === '' || ! is_string($apiHash) || $apiHash === '') {
            Log::warning('Fleet API credentials are not configured.');

            return response()->json([
                'success' => false,
                'message' => 'La integración GPS no está configurada. Verifique las credenciales del servicio.',
            ], 503);
        }

        try {
            $response = Http::timeout(15)->get($apiUrl, [
                'user_api_hash' => $apiHash,
                'lang' => 'es',
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo conectar con la plataforma GPS. Intente nuevamente.',
                ], 503);
            }

            $raw = $response->json();

            // La API devuelve un array de grupos. Aplanamos todos los items.
            $vehicles = collect($raw)
                ->flatMap(fn (array $group) => $group['items'] ?? [])
                ->map(fn (array $v) => $this->transformVehicle($v))
                ->values()
                ->all();

            return response()->json([
                'success' => true,
                'vehicles' => $vehicles,
                'refreshed_at' => now()->setTimezone('America/Guayaquil')->format('d/m/Y H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Log::error('FleetController::refresh error', ['exception' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al consultar la flota GPS.',
            ], 500);
        }
    }

    /**
     * Envía notificaciones push de prueba específicas de logística/flota
     * a todos los usuarios con suscripciones activas.
     *
     * Diseñado para demos: el cliente puede ver cómo llegarían las alertas reales.
     */
    public function testFleetAlerts(Request $request): JsonResponse
    {
        $users = User::whereHas('pushSubscriptions')->get();

        if ($users->isEmpty()) {
            return response()->json([
                'sent' => false,
                'message' => 'No hay dispositivos con notificaciones activas. Actívalas primero desde el banner.',
            ], 422);
        }

        $notifications = [
            new WebPushData(
                title: '🚛 Motor encendido sin moverse — HINO PCG9217',
                body: 'El vehículo lleva más de 10 minutos con el motor prendido sin desplazarse. Posible desperdicio de combustible.',
                severity: 'warning',
            ),
            new WebPushData(
                title: '⚡ Velocidad excesiva — TRAILER JLL UD HAA5035',
                body: 'El vehículo circula a 78 km/h. Verificar ruta y notificar al conductor.',
                severity: 'critical',
            ),
            new WebPushData(
                title: '⏱ Inactividad prolongada — VOLQUETA NPR PBE2233',
                body: 'El vehículo lleva más de 3 horas detenido con motor apagado. ¿Hay una novedad operativa?',
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

    /**
     * Transforma un vehículo del formato raw de la API al formato limpio
     * que consume el frontend. Solo expone los campos necesarios.
     *
     * @param  array<string,mixed>  $v
     * @return array<string,mixed>
     */
    private function transformVehicle(array $v): array
    {
        // Extraer sensores por tag_name para acceso fácil
        $sensors = collect($v['sensors'] ?? [])
            ->keyBy('tag_name')
            ->all();

        $batteryVehicle = $sensors['power']['value'] ?? '—';
        $ignition = $sensors['ignition']['value'] ?? 'OFF';
        $satellites = $sensors['sat']['value'] ?? '0';

        // Determinar estado semáforo
        $status = match (true) {
            ($v['online'] === 'online' || $v['online'] === 'moving') && (float) $v['speed'] > 0 => 'moving',
            ($v['online'] === 'engine') || ($v['engine_status'] ?? false) === true => 'idle',
            $v['online'] === 'offline' => 'offline',
            default => 'stopped',
        };

        return [
            'id' => $v['id'],
            'name' => $v['name'],
            'status' => $status,   // moving | idle | stopped | offline
            'speed' => (float) ($v['speed'] ?? 0),
            'stop_duration' => $v['stop_duration'] ?? '—',
            'stop_duration_sec' => (int) ($v['stop_duration_sec'] ?? 0),
            'engine_on' => $ignition === 'ON',
            'battery_vehicle' => $batteryVehicle,
            'satellites' => (int) $satellites,
            'lat' => (float) ($v['lat'] ?? 0),
            'lng' => (float) ($v['lng'] ?? 0),
            'total_distance_km' => round((float) ($v['total_distance'] ?? 0), 2),
            'expiration_date' => isset($v['device_data']['expiration_date'])
                ? substr($v['device_data']['expiration_date'], 0, 10)
                : null,
        ];
    }
}
