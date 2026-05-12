<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Warehouse\Models\BranchTransfer;
use App\Domain\Warehouse\Services\BranchTransferService;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class BranchTransferPermissionTest extends TestCase
{
    use RefreshDatabase;

    private BranchTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->service = app(BranchTransferService::class);
    }

    public function test_transfer_creation_is_limited_to_configured_roles(): void
    {
        $branch = Branch::factory()->create();

        $this->assertTrue($this->service->canCreate($this->userWithRole($branch, 'Vendedor')));
        $this->assertTrue($this->service->canCreate($this->userWithRole($branch, 'Encargado Inventario')));
        $this->assertTrue($this->service->canCreate($this->userWithRole($branch, 'Encargada Compras')));

        $this->assertFalse($this->service->canCreate($this->userWithRole($branch, 'Bodeguero')));
        $this->assertFalse($this->service->canCreate($this->userWithRole($branch, 'Contadora')));
        $this->assertFalse($this->service->canCreate(User::factory()->create()));
    }

    public function test_branch_scoped_transfer_permissions_require_matching_branch_and_status(): void
    {
        [$source, $destination, $other] = Branch::factory()->count(3)->create();

        $sourceWarehouse = $this->userWithRole($source, 'Bodeguero');
        $destinationWarehouse = $this->userWithRole($destination, 'Bodeguero');
        $otherWarehouse = $this->userWithRole($other, 'Bodeguero');
        $floatingWarehouse = $this->userWithRole(null, 'Bodeguero');

        $requested = $this->transfer($source, $destination, BranchTransfer::STATUS_REQUESTED);
        $inTransit = $this->transfer($source, $destination, BranchTransfer::STATUS_IN_TRANSIT);

        $this->assertTrue($this->service->canPrepare($requested, $sourceWarehouse));
        $this->assertFalse($this->service->canPrepare($requested, $destinationWarehouse));
        $this->assertFalse($this->service->canPrepare($requested, $otherWarehouse));
        $this->assertFalse($this->service->canPrepare($requested, $floatingWarehouse));
        $this->assertFalse($this->service->canPrepare($inTransit, $sourceWarehouse));

        $this->assertTrue($this->service->canReceive($inTransit, $destinationWarehouse));
        $this->assertFalse($this->service->canReceive($inTransit, $sourceWarehouse));
        $this->assertFalse($this->service->canReceive($inTransit, $otherWarehouse));
        $this->assertFalse($this->service->canReceive($inTransit, $floatingWarehouse));
        $this->assertFalse($this->service->canReceive($requested, $destinationWarehouse));

        $this->assertTrue($this->service->canView($requested, $sourceWarehouse));
        $this->assertTrue($this->service->canView($requested, $destinationWarehouse));
        $this->assertFalse($this->service->canView($requested, $otherWarehouse));
        $this->assertFalse($this->service->canView($requested, $floatingWarehouse));
    }

    public function test_management_transfer_permissions_require_role_branch_and_valid_status(): void
    {
        [$source, $destination] = Branch::factory()->count(2)->create();

        $inventoryUser = $this->userWithRole($source, 'Encargado Inventario');
        $purchasingUser = $this->userWithRole($destination, 'Encargada Compras');
        $seller = $this->userWithRole($destination, 'Vendedor');
        $warehouse = $this->userWithRole($source, 'Bodeguero');
        $floatingInventoryUser = $this->userWithRole(null, 'Encargado Inventario');

        $requested = $this->transfer($source, $destination, BranchTransfer::STATUS_REQUESTED);
        $received = $this->transfer($source, $destination, BranchTransfer::STATUS_RECEIVED);
        $discrepancy = $this->transfer($source, $destination, BranchTransfer::STATUS_RECEIVED_DISCREPANCY);
        $completed = $this->transfer($source, $destination, BranchTransfer::STATUS_TINI_COMPLETED);

        $this->assertTrue($this->service->canCompleteTini($received, $inventoryUser));
        $this->assertTrue($this->service->canCompleteTini($discrepancy, $purchasingUser));
        $this->assertFalse($this->service->canCompleteTini($received, $seller));
        $this->assertFalse($this->service->canCompleteTini($received, $warehouse));
        $this->assertFalse($this->service->canCompleteTini($received, $floatingInventoryUser));
        $this->assertFalse($this->service->canCompleteTini($requested, $inventoryUser));

        $this->assertTrue($this->service->canCancel($requested, $inventoryUser));
        $this->assertTrue($this->service->canCancel($received, $purchasingUser));
        $this->assertFalse($this->service->canCancel($requested, $seller));
        $this->assertFalse($this->service->canCancel($requested, $warehouse));
        $this->assertFalse($this->service->canCancel($completed, $inventoryUser));
    }

    public function test_global_owner_can_operate_without_branch_assignment(): void
    {
        [$source, $destination] = Branch::factory()->count(2)->create();
        $owner = $this->userWithRole(null, 'Dueño');

        $this->assertTrue($this->service->canCreate($owner));
        $this->assertTrue($this->service->canPrepare($this->transfer($source, $destination, BranchTransfer::STATUS_REQUESTED), $owner));
        $this->assertTrue($this->service->canReceive($this->transfer($source, $destination, BranchTransfer::STATUS_IN_TRANSIT), $owner));
        $this->assertTrue($this->service->canCompleteTini($this->transfer($source, $destination, BranchTransfer::STATUS_RECEIVED), $owner));
        $this->assertTrue($this->service->canCancel($this->transfer($source, $destination, BranchTransfer::STATUS_REQUESTED), $owner));
        $this->assertTrue($this->service->canView($this->transfer($source, $destination, BranchTransfer::STATUS_CANCELLED), $owner));
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

    private function transfer(Branch $source, Branch $destination, string $status): BranchTransfer
    {
        return new BranchTransfer([
            'source_branch_id' => $source->id,
            'destination_branch_id' => $destination->id,
            'status' => $status,
        ]);
    }
}
