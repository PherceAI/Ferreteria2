<?php

namespace Tests\Feature\Branch;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_branch_catalog_knows_store_and_warehouse_names(): void
    {
        $branches = Branch::query()
            ->whereIn('warehouse_code', ['10', '20', '30', '40'])
            ->orderBy('warehouse_code')
            ->get();

        $this->assertCount(4, $branches);
        $this->assertSame('MATRIZ / BODEGA 10', $branches[0]->display_name);
        $this->assertSame('SUCURSAL 1 / BODEGA 20', $branches[1]->display_name);
        $this->assertSame('SUCURSAL 3 / BODEGA 30', $branches[2]->display_name);
        $this->assertSame('SUCURSAL 4 / BODEGA 40', $branches[3]->display_name);

        $this->assertTrue(Branch::query()->searchAlias('BODEGA 40')->where('city', 'Macas')->exists());
        $this->assertTrue(Branch::query()->searchAlias('SUCURSAL 3')->where('warehouse_code', '30')->exists());
    }

    public function test_user_can_switch_between_assigned_branches(): void
    {
        [$branchA, $branchB] = Branch::factory()->count(2)->create();

        $user = User::factory()->create([
            'active_branch_id' => $branchA->id,
        ]);
        $user->branches()->attach([$branchA->id, $branchB->id]);

        $response = $this->actingAs($user)
            ->put(route('branch.switch'), ['branch_id' => $branchB->id]);

        $response->assertRedirect();
        $this->assertSame($branchB->id, $user->fresh()->active_branch_id);
    }

    public function test_user_cannot_switch_to_unassigned_branch(): void
    {
        [$assigned, $forbidden] = Branch::factory()->count(2)->create();

        $user = User::factory()->create([
            'active_branch_id' => $assigned->id,
        ]);
        $user->branches()->attach($assigned->id);

        $response = $this->actingAs($user)
            ->put(route('branch.switch'), ['branch_id' => $forbidden->id]);

        $response->assertForbidden();
        $this->assertSame($assigned->id, $user->fresh()->active_branch_id);
    }

    public function test_owner_with_global_access_can_switch_to_any_branch(): void
    {
        [$assigned, $unassigned] = Branch::factory()->count(2)->create();

        Permission::firstOrCreate([
            'name' => 'branches.view-all',
            'guard_name' => 'web',
        ]);
        $ownerRole = Role::firstOrCreate(['name' => 'Dueño', 'guard_name' => 'web']);
        $ownerRole->givePermissionTo('branches.view-all');

        $owner = User::factory()->create([
            'active_branch_id' => $assigned->id,
        ]);
        $owner->branches()->attach($assigned->id);
        $owner->assignRole($ownerRole);

        $response = $this->actingAs($owner)
            ->put(route('branch.switch'), ['branch_id' => $unassigned->id]);

        $response->assertRedirect();
        $this->assertSame($unassigned->id, $owner->fresh()->active_branch_id);
    }

    public function test_branch_switch_validation_rejects_unknown_branch(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch->id);

        $response = $this->actingAs($user)
            ->from(route('dashboard'))
            ->put(route('branch.switch'), ['branch_id' => 99999]);

        $response->assertSessionHasErrors('branch_id');
    }

    public function test_branch_switch_rejects_inactive_branch(): void
    {
        $active = Branch::factory()->create(['is_active' => true]);
        $inactive = Branch::factory()->create(['is_active' => false]);
        $user = User::factory()->create(['active_branch_id' => $active->id]);
        $user->branches()->attach([$active->id, $inactive->id]);

        $response = $this->actingAs($user)
            ->from(route('dashboard'))
            ->put(route('branch.switch'), ['branch_id' => $inactive->id]);

        $response->assertSessionHasErrors('branch_id');
        $this->assertSame($active->id, $user->fresh()->active_branch_id);
    }
}
