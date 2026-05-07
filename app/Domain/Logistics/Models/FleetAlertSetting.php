<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Models;

use Illuminate\Database\Eloquent\Model;

final class FleetAlertSetting extends Model
{
    protected $fillable = [
        'branch_id',
        'scope',
        'vehicle_external_id',
        'vehicle_name',
        'stopped_minutes',
        'idle_minutes',
        'speed_limit_kph',
        'min_voltage_12',
        'min_voltage_24',
        'stale_minutes',
        'gps_signal_enabled',
        'voltage_enabled',
        'route_stop_enabled',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'stopped_minutes' => 'integer',
        'idle_minutes' => 'integer',
        'speed_limit_kph' => 'integer',
        'min_voltage_12' => 'decimal:2',
        'min_voltage_24' => 'decimal:2',
        'stale_minutes' => 'integer',
        'gps_signal_enabled' => 'boolean',
        'voltage_enabled' => 'boolean',
        'route_stop_enabled' => 'boolean',
    ];
}
