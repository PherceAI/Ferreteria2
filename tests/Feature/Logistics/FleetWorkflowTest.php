<?php

declare(strict_types=1);

namespace Tests\Feature\Logistics;

use App\Domain\Logistics\Models\FleetAlertSetting;
use App\Domain\Logistics\Models\FleetVehicleSnapshot;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class FleetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_refresh_reads_gps_data_builds_alerts_and_stores_snapshots(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->userForBranch($branch, 'Dueño');

        config([
            'services.ubika.devices_url' => 'https://gps.example.test/devices',
            'services.ubika.user_api_hash' => 'demo-hash',
        ]);

        Http::fake([
            'gps.example.test/*' => Http::response([
                [
                    'items' => [
                        $this->vehiclePayload(
                            id: 101,
                            name: 'MULA HINO PBW2792',
                            online: 'moving',
                            speed: 95,
                            voltage: '0 V',
                            satellites: '4',
                        ),
                        $this->vehiclePayload(
                            id: 202,
                            name: 'NPR HBD9032',
                            online: 'offline',
                            speed: 0,
                            voltage: '12.6 V',
                            satellites: '9',
                        ),
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->postJson(route('logistics.fleet.refresh'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('kpis.total', 2)
            ->assertJsonPath('vehicles.0.name', 'MULA HINO PBW2792')
            ->assertJsonFragment([
                'title' => 'GPS sin energia del vehiculo',
                'severity' => 'critical',
            ]);

        $this->assertSame(2, FleetVehicleSnapshot::query()->count());
    }

    public function test_fleet_alert_settings_are_saved_per_active_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->userForBranch($branch, 'Dueño');

        $this->actingAs($user)
            ->postJson(route('logistics.fleet.alert-settings'), [
                'scope' => 'vehicle',
                'vehicle_external_id' => '101',
                'vehicle_name' => 'MULA HINO PBW2792',
                'stopped_minutes' => 45,
                'idle_minutes' => 8,
                'speed_limit_kph' => 75,
                'min_voltage_12' => 11.5,
                'min_voltage_24' => 23.2,
                'stale_minutes' => 20,
                'gps_signal_enabled' => true,
                'voltage_enabled' => true,
                'route_stop_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('saved', true);

        $this->assertDatabaseHas('fleet_alert_settings', [
            'branch_id' => $branch->id,
            'scope' => 'vehicle',
            'vehicle_external_id' => '101',
            'vehicle_name' => 'MULA HINO PBW2792',
            'speed_limit_kph' => 75,
        ]);

        $this->assertFalse((bool) FleetAlertSetting::query()->firstOrFail()->route_stop_enabled);
    }

    public function test_seller_cannot_refresh_or_configure_fleet(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->userForBranch($branch, 'Vendedor');

        $this->actingAs($user)
            ->postJson(route('logistics.fleet.refresh'))
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('logistics.fleet.alert-settings'), [])
            ->assertForbidden();
    }

    private function userForBranch(Branch $branch, string $role): User
    {
        $user = User::factory()->create([
            'active_branch_id' => $branch->id,
        ]);

        $user->branches()->syncWithoutDetaching([$branch->id]);
        $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));

        return $user;
    }

    private function vehiclePayload(
        int $id,
        string $name,
        string $online,
        float $speed,
        string $voltage,
        string $satellites,
    ): array {
        return [
            'id' => $id,
            'uniqueId' => "imei-{$id}",
            'name' => $name,
            'online' => $online,
            'speed' => $speed,
            'course' => 90,
            'stop_duration' => '0 min',
            'stop_duration_sec' => 0,
            'engine_status' => $speed > 0,
            'lat' => -2.9,
            'lng' => -79.0,
            'total_distance' => 15420,
            'time' => now('America/Guayaquil')->format('d-m-Y H:i:s'),
            'tail' => [['lat' => -2.9, 'lng' => -79.0]],
            'sensors' => [
                ['tag_name' => 'power', 'value' => $voltage],
                ['tag_name' => 'sat', 'value' => $satellites],
                ['tag_name' => 'ignition', 'value' => $speed > 0 ? 'ON' : 'OFF'],
                ['tag_name' => 'blocked', 'value' => 'OFF'],
            ],
            'device_data' => [
                'plate_number' => $name,
                'imei' => "imei-{$id}",
                'expiration_date' => now('America/Guayaquil')->addDays(90)->format('Y-m-d'),
                'min_moving_speed' => 6,
            ],
        ];
    }
}
