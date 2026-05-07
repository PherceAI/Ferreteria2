<?php

declare(strict_types=1);

namespace App\Domain\Logistics\Models;

use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

final class FleetVehicleSnapshot extends Model
{
    use Auditable;

    protected $table = 'pherce_intel.fleet_vehicle_snapshots';

    protected $fillable = [
        'external_id',
        'unique_id',
        'name',
        'status',
        'online_raw',
        'speed',
        'latitude',
        'longitude',
        'course',
        'engine_on',
        'blocked',
        'satellites',
        'vehicle_voltage',
        'gps_battery_percent',
        'stop_duration_sec',
        'total_distance_km',
        'engine_hours_total',
        'reported_at',
        'tail',
        'raw_flags',
    ];

    protected function casts(): array
    {
        return [
            'blocked' => 'boolean',
            'course' => 'integer',
            'engine_hours_total' => 'decimal:2',
            'engine_on' => 'boolean',
            'gps_battery_percent' => 'integer',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'reported_at' => 'datetime',
            'satellites' => 'integer',
            'speed' => 'decimal:2',
            'stop_duration_sec' => 'integer',
            'tail' => 'array',
            'total_distance_km' => 'decimal:2',
            'vehicle_voltage' => 'decimal:2',
            'raw_flags' => 'array',
        ];
    }
}
