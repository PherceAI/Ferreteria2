<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Branch;
use App\Models\User;
use App\Support\UserHome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class UserHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_user_goes_directly_to_reception(): void
    {
        $branch = Branch::factory()->create();
        $user = $this->userWithRole($branch, 'Bodeguero');

        $this->assertSame(route('purchasing.receipt.index', absolute: false), UserHome::pathFor($user));
    }

    public function test_global_and_administrative_users_go_to_dashboard(): void
    {
        $branch = Branch::factory()->create();

        $this->assertSame(route('dashboard', absolute: false), UserHome::pathFor($this->userWithRole(null, 'Dueño')));
        $this->assertSame(route('dashboard', absolute: false), UserHome::pathFor($this->userWithRole($branch, 'Encargada Compras')));
    }

    private function userWithRole(?Branch $branch, string $roleName): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $user = User::factory()->create([
            'active_branch_id' => $branch?->id,
        ]);

        $user->assignRole($role);

        if ($branch instanceof Branch) {
            $user->branches()->attach($branch->id);
        }

        return $user->refresh();
    }
}
