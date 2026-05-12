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

    public function test_global_user_can_create_and_deactivate_internal_users(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'branches.view-all', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $sellerRole = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['active_branch_id' => $branch->id]);
        $owner->branches()->attach($branch);
        $owner->assignRole($role);

        $this->actingAs($owner)
            ->post(route('team.employees.store'), [
                'name' => 'Empleado Interno',
                'email' => 'empleado@example.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'branch_ids' => [$branch->id],
                'role_names' => [$sellerRole->name],
                'is_active' => true,
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'empleado@example.test')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertTrue($user->branches()->whereKey($branch->id)->exists());
        $this->assertTrue($user->hasRole('Vendedor'));

        $this->actingAs($owner)
            ->patch(route('team.employees.status.update', $user), ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->is_active);

        $this->actingAs($owner)
            ->delete(route('team.employees.destroy', $user))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
            'active_branch_id' => null,
        ]);
        $this->assertFalse($user->branches()->whereKey($branch->id)->exists());
        $this->assertFalse($user->fresh()->hasAnyRole(['Vendedor', 'Owner']));
    }

    public function test_branch_view_all_permission_alone_cannot_manage_employees(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'branches.view-all', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Auditor Sucursales', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branch = Branch::factory()->create();
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch);
        $user->assignRole($role);

        $this->actingAs($user)
            ->get(route('team.employees.index'))
            ->assertForbidden();
    }

    public function test_owner_cannot_modify_their_own_roles_or_branches(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'branches.view-all', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['active_branch_id' => $branch->id]);
        $owner->branches()->attach($branch);
        $owner->assignRole($role);

        $this->actingAs($owner)
            ->put(route('team.employees.roles.update', $owner), ['role_names' => []])
            ->assertStatus(422);

        $this->actingAs($owner)
            ->put(route('team.employees.branches.update', $owner), ['branch_ids' => []])
            ->assertStatus(422);

        $this->assertTrue($owner->fresh()->hasRole('Owner'));
        $this->assertTrue($owner->branches()->whereKey($branch->id)->exists());
    }
}
