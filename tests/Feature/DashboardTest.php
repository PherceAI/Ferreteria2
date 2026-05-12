<?php

namespace Tests\Feature;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch);
        InventoryProduct::query()->create([
            'branch_id' => $branch->id,
            'code' => 'CEM-001',
            'name' => 'Cemento gris',
            'unit' => 'UND',
            'current_stock' => 2,
            'min_stock' => 5,
            'cost' => 7.5,
            'total_cost' => 15,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('overview.summary.products', 1)
                ->where('overview.summary.lowStock', 1)
                ->where('overview.summary.inventoryValue', 15)
            );
    }

    public function test_authenticated_users_without_branch_are_redirected()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('branch.required'));
    }
}
