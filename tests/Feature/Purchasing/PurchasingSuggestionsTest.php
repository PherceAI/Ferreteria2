<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PurchasingSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchasing_suggestions_are_based_on_real_branch_inventory(): void
    {
        [$activeBranch, $otherBranch] = Branch::factory()->count(2)->create();
        $user = User::factory()->create(['active_branch_id' => $activeBranch->id]);
        $user->branches()->attach($activeBranch);
        $user->assignRole(Role::firstOrCreate(['name' => 'Encargado Inventario', 'guard_name' => 'web']));

        InventoryProduct::query()->create([
            'branch_id' => $activeBranch->id,
            'code' => 'PVC-001',
            'name' => 'Tubo PVC',
            'unit' => 'UND',
            'current_stock' => 2,
            'min_stock' => 5,
            'cost' => 3,
            'last_purchase_cost' => 2.5,
            'supplier_name' => 'Proveedor PVC',
        ]);

        InventoryProduct::query()->create([
            'branch_id' => $otherBranch->id,
            'code' => 'PVC-001',
            'name' => 'Tubo PVC',
            'unit' => 'UND',
            'current_stock' => 30,
            'min_stock' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('purchasing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/index')
                ->where('stats.activeSuggestions', 1)
                ->where('stats.estimatedRestockValue', 20)
                ->where('suggestions.0.code', 'PVC-001')
                ->where('suggestions.0.suggestedQty', 8)
                ->where('stockMatrix.0.total', 32)
            );
    }

    public function test_seller_cannot_view_purchasing_suggestions(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch);
        $user->assignRole(Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->get(route('purchasing.index'))
            ->assertForbidden();
    }
}
