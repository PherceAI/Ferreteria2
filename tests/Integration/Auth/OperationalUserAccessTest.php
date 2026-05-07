<?php

namespace Tests\Integration\Auth;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationalUserAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_warehouse_user_lands_on_physical_reception_instead_of_dashboard(): void
    {
        $branch = Branch::factory()->create(['is_headquarters' => true]);
        $role = Role::firstOrCreate(['name' => 'Bodeguero', 'guard_name' => 'web']);
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch);
        $user->assignRole($role);

        $login = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $login->assertRedirect(route('purchasing.receipt.index', absolute: false));

        $dashboard = $this->actingAs($user)->get(route('dashboard'));

        $dashboard->assertStatus(302);
        $this->assertSame(
            route('purchasing.receipt.index', absolute: false),
            parse_url((string) $dashboard->headers->get('Location'), PHP_URL_PATH),
        );
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_global_user_can_deactivate_and_delete_test_users(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'branches.view-all', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['active_branch_id' => $branch->id]);
        $owner->branches()->attach($branch);
        $owner->assignRole($role);

        $user = User::factory()->create();

        $this->actingAs($owner)
            ->patch(route('team.employees.status.update', $user), ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($owner)
            ->delete(route('team.employees.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
