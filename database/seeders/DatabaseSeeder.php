<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $viewAllBranches = Permission::firstOrCreate([
            'name' => 'branches.view-all',
            'guard_name' => 'web',
        ]);

        $ownerRole = Role::firstOrCreate(['name' => 'Dueño', 'guard_name' => 'web']);
        $accountingRole = Role::firstOrCreate(['name' => 'Contadora', 'guard_name' => 'web']);
        $purchasingRole = Role::firstOrCreate(['name' => 'Encargada Compras', 'guard_name' => 'web']);
        $inventoryRole = Role::firstOrCreate(['name' => 'Encargado Inventario', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Bodeguero', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);

        $ownerRole->givePermissionTo($viewAllBranches);
        $accountingRole->givePermissionTo($viewAllBranches);
        $purchasingRole->givePermissionTo($viewAllBranches);
        $inventoryRole->givePermissionTo($viewAllBranches);

        $branches = collect([
            [
                'name' => 'MATRIZ',
                'code' => 'B10',
                'warehouse_name' => 'BODEGA 10',
                'warehouse_code' => '10',
                'city' => 'Riobamba',
                'is_headquarters' => true,
            ],
            [
                'name' => 'SUCURSAL 1',
                'code' => 'B20',
                'warehouse_name' => 'BODEGA 20',
                'warehouse_code' => '20',
                'city' => 'Riobamba',
                'is_headquarters' => false,
            ],
            [
                'name' => 'SUCURSAL 3',
                'code' => 'B30',
                'warehouse_name' => 'BODEGA 30',
                'warehouse_code' => '30',
                'city' => 'Riobamba',
                'is_headquarters' => false,
            ],
            [
                'name' => 'SUCURSAL 4',
                'code' => 'B40',
                'warehouse_name' => 'BODEGA 40',
                'warehouse_code' => '40',
                'city' => 'Macas',
                'is_headquarters' => false,
            ],
        ])->map(fn (array $branch) => Branch::firstOrCreate(
            ['code' => $branch['code']],
            [...$branch, 'is_active' => true],
        ));

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'active_branch_id' => $branches->first()?->getKey(),
        ]);

        $user->branches()->syncWithoutDetaching($branches->pluck('id'));
        $user->assignRole($ownerRole);
    }
}
