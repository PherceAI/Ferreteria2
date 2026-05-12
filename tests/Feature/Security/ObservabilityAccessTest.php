<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ObservabilityAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_observability_dashboards_require_auth_middleware(): void
    {
        $this->assertContains('auth', config('telescope.middleware'));
        $this->assertContains('auth', config('pulse.middleware'));
        $this->assertContains('auth', config('horizon.middleware'));
        $this->assertFalse((bool) config('telescope.enabled'));
    }

    public function test_observability_gates_allow_only_internal_observers(): void
    {
        $seller = User::factory()->create();
        $seller->assignRole(Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']));

        $owner = User::factory()->create();
        $owner->assignRole(Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));

        $this->assertFalse(Gate::forUser($seller)->allows('viewPulse'));
        $this->assertFalse(Gate::forUser($seller)->allows('viewTelescope'));
        $this->assertFalse(Gate::forUser($seller)->allows('viewHorizon'));

        $this->assertTrue(Gate::forUser($owner)->allows('viewPulse'));
        $this->assertTrue(Gate::forUser($owner)->allows('viewTelescope'));
        $this->assertTrue(Gate::forUser($owner)->allows('viewHorizon'));
    }
}
