<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Services;

use App\Domain\Logistics\Models\FleetAlertSetting;
use App\Domain\Logistics\Models\FleetVehicleSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class FleetTelemetryService
{
    private const SPEED_LIMIT_KPH = 80;

    private const IDLE_ALERT_SECONDS = 600;

    private const STOPPED_ALERT_SECONDS = 3600;

    private const STALE_ALERT_SECONDS = 1800;

    private const LOW_SATELLITES = 6;

    private const SERVICE_INTERVAL_KM = 5000;

    private const SERVICE_SOON_KM = 500;

    /**
     * @return array<string, mixed>
     */
    public function buildDashboard(): array
    {
        $vehicles = $this->fetchVehicles();
        $now = CarbonImmutable::now('America/Guayaquil');

        $snapshots = $vehicles
            ->map(fn (array $vehicle): array => $this->snapshotPayload($vehicle))
            ->all();

        FleetVehicleSnapshot::query()->insert($snapshots);

        $history = $this->buildHistory($vehicles);
        $alertSettings = $this->alertSettings($vehicles);
        $alerts = $this->buildAlerts($vehicles, $now, $alertSettings);
        $maintenance = $this->buildMaintenance($vehicles);
        $kpis = $this->buildKpis($vehicles, $alerts, $maintenance);
        $recommendations = $this->buildRecommendations($alerts, $maintenance, $history);

        return [
            'vehicles' => $vehicles->values()->all(),
            'alerts' => $alerts,
            'maintenance' => $maintenance,
            'kpis' => $kpis,
            'history' => $history,
            'recommendations' => $recommendations,
            'refreshed_at' => $now->format('d/m/Y H:i:s'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchVehicles(): Collection
    {
        $apiUrl = config('services.ubika.devices_url');
        $apiHash = config('services.ubika.user_api_hash');

        if (! is_string($apiUrl) || $apiUrl === '' || ! is_string($apiHash) || $apiHash === '') {
            throw new RuntimeException('La integracion GPS no esta configurada.');
        }

        $response = Http::timeout(20)->get($apiUrl, [
            'user_api_hash' => $apiHash,
            'lang' => 'es',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo conectar con la plataforma GPS.');
        }

        $raw = $response->json();

        if (! is_array($raw)) {
            throw new RuntimeException('La plataforma GPS devolvio un formato inesperado.');
        }

        return collect($raw)
            ->flatMap(fn (array $group): array => $group['items'] ?? [])
            ->map(fn (array $vehicle): array => $this->transformVehicle($vehicle));
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function transformVehicle(array $v): array
    {
        $sensors = collect($v['sensors'] ?? [])->keyBy('tag_name');
        $device = is_array($v['device_data'] ?? null) ? $v['device_data'] : [];

        $vehicleVoltage = $this->parseNumber($sensors->get('power')['value'] ?? null);
        $gpsBatteryPercent = $this->parseInteger($sensors->get('batterylevel')['value'] ?? null);
        $satellites = $this->parseInteger($sensors->get('sat')['value'] ?? null);
        $engineOn = $this->isOn($sensors->get('ignition')['value'] ?? null) || (bool) ($v['engine_status'] ?? false);
        $blocked = $this->isOn($sensors->get('blocked')['value'] ?? null);
        $speed = (float) ($v['speed'] ?? 0);
        $stopDurationSec = (int) ($v['stop_duration_sec'] ?? 0);
        $reportedAt = $this->reportedAt($v);
        $expirationDate = isset($device['expiration_date']) ? substr((string) $device['expiration_date'], 0, 10) : null;
        $totalDistanceKm = round((float) ($v['total_distance'] ?? 0), 2);

        $status = match (true) {
            in_array($v['online'] ?? null, ['online', 'moving'], true) && $speed > 0 => 'moving',
            $engineOn && $speed <= 0 => 'idle',
            ($v['online'] ?? null) === 'offline' => 'offline',
            default => 'stopped',
        };

        return [
            'id' => (int) ($v['id'] ?? 0),
            'external_id' => (string) ($v['id'] ?? ''),
            'unique_id' => (string) ($v['uniqueId'] ?? $device['imei'] ?? $device['traccar_device_id'] ?? ''),
            'name' => (string) ($v['name'] ?? 'Vehiculo sin nombre'),
            'plate' => $this->plate($v, $device),
            'status' => $status,
            'online_raw' => (string) ($v['online'] ?? ''),
            'speed' => $speed,
            'course' => isset($v['course']) ? (int) $v['course'] : null,
            'stop_duration' => (string) ($v['stop_duration'] ?? '0 min'),
            'stop_duration_sec' => $stopDurationSec,
            'engine_on' => $engineOn,
            'blocked' => $blocked,
            'battery_vehicle' => $vehicleVoltage !== null ? number_format($vehicleVoltage, 1).' V' : '-',
            'vehicle_voltage' => $vehicleVoltage,
            'voltage_system' => $this->voltageSystem($vehicleVoltage),
            'gps_battery_percent' => $gpsBatteryPercent,
            'satellites' => $satellites ?? 0,
            'lat' => (float) ($v['lat'] ?? 0),
            'lng' => (float) ($v['lng'] ?? 0),
            'total_distance_km' => $totalDistanceKm,
            'engine_hours_total' => $this->parseEngineHours($v['engine_hours'] ?? $device['engine_hours'] ?? null),
            'expiration_date' => $expirationDate,
            'reported_at' => $reportedAt?->toIso8601String(),
            'reported_at_label' => $reportedAt?->setTimezone('America/Guayaquil')->format('d/m/Y H:i:s'),
            'data_age_sec' => $reportedAt !== null ? $reportedAt->diffInSeconds(CarbonImmutable::now('UTC')) : null,
            'tail' => $this->tail($v['tail'] ?? []),
            'map_url' => 'https://maps.google.com/?q='.(float) ($v['lat'] ?? 0).','.(float) ($v['lng'] ?? 0),
            'movement_threshold_kph' => (int) ($device['min_moving_speed'] ?? 6),
            'risk_score' => 0,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $vehicles
     * @return array<string, mixed>
     */
    private function buildKpis(Collection $vehicles, array $alerts, array $maintenance): array
    {
        return [
            'total' => $vehicles->count(),
            'moving' => $vehicles->where('status', 'moving')->count(),
            'idle' => $vehicles->where('status', 'idle')->count(),
            'stopped' => $vehicles->where('status', 'stopped')->count(),
            'offline' => $vehicles->where('status', 'offline')->count(),
            'critical_alerts' => collect($alerts)->where('severity', 'critical')->count(),
            'warning_alerts' => collect($alerts)->where('severity', 'warning')->count(),
            'electrical_risks' => collect($alerts)->where('category', 'electrical')->count(),
            'maintenance_due' => collect($maintenance)->whereIn('status', ['due', 'soon'])->count(),
            'avg_satellites' => round((float) $vehicles->avg('satellites'), 1),
            'total_distance_km' => round((float) $vehicles->sum('total_distance_km'), 0),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $vehicles
     * @return array<int, array<string, mixed>>
     */
    private function buildAlerts(Collection $vehicles, CarbonImmutable $now, array $alertSettings): array
    {
        $alerts = [];

        foreach ($vehicles as $vehicle) {
            $name = $vehicle['name'];
            $score = 0;
            $settings = $alertSettings[$vehicle['external_id']] ?? $this->defaultAlertSettings();

            if ($settings['voltage_enabled'] && ($vehicle['vehicle_voltage'] ?? null) === 0.0) {
                $alerts[] = $this->alert($name, 'critical', 'electrical', 'GPS sin energia del vehiculo', 'Revisar bateria, fusible o desconexion del rastreador antes de despachar la unidad.');
                $score += 30;
            } elseif ($settings['voltage_enabled'] && $this->isLowVoltage($vehicle, $settings)) {
                $alerts[] = $this->alert($name, 'warning', 'electrical', 'Bateria baja', 'Programar revision electrica: el voltaje esta por debajo del umbral operativo.');
                $score += 18;
            }

            if ($settings['gps_signal_enabled'] && ($vehicle['data_age_sec'] ?? 0) > $settings['stale_minutes'] * 60) {
                $alerts[] = $this->alert($name, 'critical', 'gps', 'GPS sin reporte reciente', 'No tomar decisiones de ruta con esta posicion hasta validar senal o equipo.');
                $score += 25;
            }

            if ($settings['gps_signal_enabled'] && ($vehicle['satellites'] ?? 0) > 0 && ($vehicle['satellites'] ?? 0) < self::LOW_SATELLITES) {
                $alerts[] = $this->alert($name, 'warning', 'gps', 'Baja precision GPS', 'Revisar antena, ubicacion del equipo o cobertura de la zona.');
                $score += 10;
            }

            if (($vehicle['blocked'] ?? false) === true) {
                $alerts[] = $this->alert($name, 'critical', 'security', 'Motor bloqueado', 'Confirmar si el bloqueo fue autorizado o si hay una novedad de seguridad.');
                $score += 25;
            }

            if ($settings['route_stop_enabled'] && ($vehicle['status'] ?? '') === 'idle' && ($vehicle['stop_duration_sec'] ?? 0) > $settings['idle_minutes'] * 60) {
                $alerts[] = $this->alert($name, 'warning', 'cost', 'Ralentí improductivo', 'Motor encendido sin avance: llamar al conductor para reducir combustible perdido.');
                $score += 12;
            }

            if (($vehicle['speed'] ?? 0) > $settings['speed_limit_kph']) {
                $alerts[] = $this->alert($name, 'critical', 'safety', 'Velocidad excesiva', 'Contactar al conductor y verificar ruta, carga y condiciones de via.');
                $score += 25;
            }

            if ($settings['route_stop_enabled'] && ($vehicle['status'] ?? '') === 'stopped' && ($vehicle['stop_duration_sec'] ?? 0) > $settings['stopped_minutes'] * 60) {
                $alerts[] = $this->alert($name, 'info', 'operations', 'Inactividad prolongada', 'Confirmar si la unidad esta cargando, en taller, sin conductor o disponible para reasignar.');
                $score += min(20, (int) floor(($vehicle['stop_duration_sec'] ?? 0) / 3600) * 2);
            }

            if (is_string($vehicle['expiration_date'] ?? null)) {
                $expiration = CarbonImmutable::parse($vehicle['expiration_date'], 'America/Guayaquil');
                $days = $now->diffInDays($expiration, false);

                if ($days <= 60) {
                    $alerts[] = $this->alert($name, $days <= 15 ? 'critical' : 'warning', 'renewal', 'Servicio GPS por vencer', "Renovar en {$days} dias para no perder rastreo.");
                    $score += $days <= 15 ? 18 : 8;
                }
            }

            $vehicle['risk_score'] = min(100, $score);
        }

        return collect($alerts)
            ->sortBy(fn (array $alert): int => ['critical' => 0, 'warning' => 1, 'info' => 2][$alert['severity']] ?? 3)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $vehicles
     * @return array<string, array<string, mixed>>
     */
    private function alertSettings(Collection $vehicles): array
    {
        $branchId = (int) Context::get('branch_id');
        $defaults = $this->defaultAlertSettings();

        $settings = FleetAlertSetting::query()
            ->where('branch_id', $branchId)
            ->where(function ($query) use ($vehicles): void {
                $query
                    ->where('scope', 'global')
                    ->orWhereIn('vehicle_external_id', $vehicles->pluck('external_id')->filter()->values()->all());
            })
            ->get();

        $global = $settings->firstWhere('scope', 'global');
        $globalSettings = $global !== null ? $this->settingPayload($global, $defaults) : $defaults;
        $vehicleSettings = $settings
            ->where('scope', 'vehicle')
            ->keyBy('vehicle_external_id');

        return $vehicles
            ->mapWithKeys(function (array $vehicle) use ($globalSettings, $vehicleSettings): array {
                $setting = $vehicleSettings->get($vehicle['external_id']);

                return [
                    $vehicle['external_id'] => $setting !== null
                        ? $this->settingPayload($setting, $globalSettings)
                        : $globalSettings,
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAlertSettings(): array
    {
        return [
            'stopped_minutes' => (int) (self::STOPPED_ALERT_SECONDS / 60),
            'idle_minutes' => (int) (self::IDLE_ALERT_SECONDS / 60),
            'speed_limit_kph' => self::SPEED_LIMIT_KPH,
            'min_voltage_12' => 11.8,
            'min_voltage_24' => 23.5,
            'stale_minutes' => (int) (self::STALE_ALERT_SECONDS / 60),
            'gps_signal_enabled' => true,
            'voltage_enabled' => true,
            'route_stop_enabled' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    private function settingPayload(FleetAlertSetting $setting, array $fallback): array
    {
        return [
            'stopped_minutes' => $setting->stopped_minutes ?? $fallback['stopped_minutes'],
            'idle_minutes' => $setting->idle_minutes ?? $fallback['idle_minutes'],
            'speed_limit_kph' => $setting->speed_limit_kph ?? $fallback['speed_limit_kph'],
            'min_voltage_12' => (float) ($setting->min_voltage_12 ?? $fallback['min_voltage_12']),
            'min_voltage_24' => (float) ($setting->min_voltage_24 ?? $fallback['min_voltage_24']),
            'stale_minutes' => $setting->stale_minutes ?? $fallback['stale_minutes'],
            'gps_signal_enabled' => (bool) ($setting->gps_signal_enabled ?? $fallback['gps_signal_enabled']),
            'voltage_enabled' => (bool) ($setting->voltage_enabled ?? $fallback['voltage_enabled']),
            'route_stop_enabled' => (bool) ($setting->route_stop_enabled ?? $fallback['route_stop_enabled']),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $vehicles
     * @return array<int, array<string, mixed>>
     */
    private function buildMaintenance(Collection $vehicles): array
    {
        return $vehicles
            ->map(function (array $vehicle): array {
                $distance = (float) ($vehicle['total_distance_km'] ?? 0);
                $nextService = max(self::SERVICE_INTERVAL_KM, (int) ceil($distance / self::SERVICE_INTERVAL_KM) * self::SERVICE_INTERVAL_KM);
                $remaining = $nextService - $distance;
                $status = $remaining <= 0 ? 'due' : ($remaining <= self::SERVICE_SOON_KM ? 'soon' : 'ok');

                return [
                    'vehicle_name' => $vehicle['name'],
                    'current_km' => round($distance, 0),
                    'next_service_km' => $nextService,
                    'remaining_km' => round($remaining, 0),
                    'status' => $status,
                    'action' => match ($status) {
                        'due' => 'Agendar mantenimiento antes del siguiente despacho.',
                        'soon' => 'Reservar taller y repuestos esta semana.',
                        default => 'Sin accion inmediata.',
                    },
                ];
            })
            ->sortBy(fn (array $item): float => (float) $item['remaining_km'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $vehicles
     * @return array<string, mixed>
     */
    private function buildHistory(Collection $vehicles): array
    {
        $ids = $vehicles->pluck('external_id')->filter()->values()->all();
        $since = now()->subDays(7);

        $rows = FleetVehicleSnapshot::query()
            ->select([
                'external_id',
                DB::raw('MIN(total_distance_km) as start_km'),
                DB::raw('MAX(total_distance_km) as end_km'),
                DB::raw('COUNT(*) as samples'),
            ])
            ->whereIn('external_id', $ids)
            ->where('reported_at', '>=', $since)
            ->groupBy('external_id')
            ->get()
            ->keyBy('external_id');

        $activity = $vehicles
            ->map(function (array $vehicle) use ($rows): array {
                $row = $rows->get($vehicle['external_id']);
                $kmDelta = $row !== null ? max(0, (float) $row->end_km - (float) $row->start_km) : 0;

                return [
                    'vehicle_name' => $vehicle['name'],
                    'km_7d' => round($kmDelta, 1),
                    'samples' => (int) ($row->samples ?? 0),
                ];
            })
            ->sortByDesc('km_7d')
            ->values()
            ->all();

        return [
            'activity_7d' => $activity,
            'most_used' => $activity[0] ?? null,
            'least_used' => collect($activity)->sortBy('km_7d')->first(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildRecommendations(array $alerts, array $maintenance, array $history): array
    {
        $critical = collect($alerts)->where('severity', 'critical');
        $electrical = collect($alerts)->where('category', 'electrical');
        $maintenanceDue = collect($maintenance)->whereIn('status', ['due', 'soon']);

        return collect([
            $critical->isNotEmpty() ? [
                'title' => 'Atender riesgos criticos antes de despachar',
                'body' => 'Hay '.$critical->count().' novedades criticas. Priorizar seguridad, energia GPS y velocidad.',
                'type' => 'critical',
            ] : null,
            $electrical->isNotEmpty() ? [
                'title' => 'Revisar alimentacion electrica de GPS',
                'body' => $electrical->count().' unidades muestran voltaje riesgoso o desconexion.',
                'type' => 'warning',
            ] : null,
            $maintenanceDue->isNotEmpty() ? [
                'title' => 'Planificar mantenimiento preventivo',
                'body' => $maintenanceDue->count().' unidades estan cerca o pasadas del hito de referencia.',
                'type' => 'warning',
            ] : null,
            ($history['least_used']['km_7d'] ?? 1) <= 0 ? [
                'title' => 'Validar unidades subutilizadas',
                'body' => 'Hay vehiculos sin kilometraje reciente. Confirmar si estan disponibles, en taller o sin asignacion.',
                'type' => 'info',
            ] : null,
        ])
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @return array<string, mixed>
     */
    private function snapshotPayload(array $vehicle): array
    {
        return [
            'external_id' => $vehicle['external_id'],
            'unique_id' => $vehicle['unique_id'] !== '' ? $vehicle['unique_id'] : null,
            'name' => $vehicle['name'],
            'status' => $vehicle['status'],
            'online_raw' => $vehicle['online_raw'],
            'speed' => $vehicle['speed'],
            'latitude' => $vehicle['lat'],
            'longitude' => $vehicle['lng'],
            'course' => $vehicle['course'],
            'engine_on' => $vehicle['engine_on'],
            'blocked' => $vehicle['blocked'],
            'satellites' => $vehicle['satellites'],
            'vehicle_voltage' => $vehicle['vehicle_voltage'],
            'gps_battery_percent' => $vehicle['gps_battery_percent'],
            'stop_duration_sec' => $vehicle['stop_duration_sec'],
            'total_distance_km' => $vehicle['total_distance_km'],
            'engine_hours_total' => $vehicle['engine_hours_total'],
            'reported_at' => $vehicle['reported_at'],
            'tail' => json_encode($vehicle['tail']),
            'raw_flags' => json_encode([
                'voltage_system' => $vehicle['voltage_system'],
                'movement_threshold_kph' => $vehicle['movement_threshold_kph'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function alert(string $vehicleName, string $severity, string $category, string $title, string $action): array
    {
        return compact('vehicleName', 'severity', 'category', 'title', 'action');
    }

    private function plate(array $vehicle, array $device): string
    {
        $plate = trim((string) ($device['plate_number'] ?? ''));

        return $plate !== '' ? $plate : (string) ($vehicle['name'] ?? '');
    }

    private function isOn(mixed $value): bool
    {
        return in_array(strtoupper(trim((string) $value)), ['ON', 'TRUE', '1', 'YES'], true);
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '-') {
            return null;
        }

        preg_match('/-?\d+(?:[.,]\d+)?/', (string) $value, $matches);

        return isset($matches[0]) ? (float) str_replace(',', '.', $matches[0]) : null;
    }

    private function parseInteger(mixed $value): ?int
    {
        $number = $this->parseNumber($value);

        return $number !== null ? (int) round($number) : null;
    }

    private function parseEngineHours(mixed $value): ?float
    {
        $number = $this->parseNumber($value);

        if ($number === null || $number <= 0) {
            return null;
        }

        return $number > 10000 ? round($number / 3600, 2) : round($number, 2);
    }

    private function voltageSystem(?float $voltage): string
    {
        if ($voltage === null) {
            return 'unknown';
        }

        if ($voltage === 0.0) {
            return 'disconnected';
        }

        return $voltage >= 18 ? '24v' : '12v';
    }

    /**
     * @param  array<string, mixed>  $vehicle
     */
    private function isLowVoltage(array $vehicle, array $settings): bool
    {
        $voltage = $vehicle['vehicle_voltage'] ?? null;

        if (! is_float($voltage) && ! is_int($voltage)) {
            return false;
        }

        return match ($vehicle['voltage_system']) {
            '24v' => $voltage < $settings['min_voltage_24'],
            '12v' => $voltage < $settings['min_voltage_12'],
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $vehicle
     */
    private function reportedAt(array $vehicle): ?CarbonImmutable
    {
        $time = $vehicle['time'] ?? null;

        if (is_string($time) && $time !== '') {
            try {
                return CarbonImmutable::createFromFormat('d-m-Y H:i:s', $time, 'America/Guayaquil')?->utc();
            } catch (\Throwable) {
                // Fall back to timestamp below.
            }
        }

        if (isset($vehicle['timestamp']) && is_numeric($vehicle['timestamp'])) {
            return CarbonImmutable::createFromTimestamp((int) $vehicle['timestamp'], 'UTC');
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $tail
     * @return array<int, array{lat:float,lng:float}>
     */
    private function tail(array $tail): array
    {
        return collect($tail)
            ->filter(fn (mixed $point): bool => is_array($point))
            ->map(fn (array $point): array => [
                'lat' => (float) ($point['lat'] ?? $point[0] ?? 0),
                'lng' => (float) ($point['lng'] ?? $point[1] ?? 0),
            ])
            ->take(15)
            ->values()
            ->all();
    }
}
