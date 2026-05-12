<?php

declare(strict_types=1);

namespace Tests\Feature\Warehouse;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Warehouse\Models\BranchTransfer;
use App\Domain\Warehouse\Models\BranchTransferEvent;
use App\Domain\Warehouse\Services\BranchTransferService;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class BranchTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_creates_multi_item_transfer_request_from_active_branch(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $product = $this->product($source, 'TAL-001', 'Taladro percutor');

        $this->actingAs($seller)
            ->post(route('inventory.transfers.store'), [
                'source_branch_id' => $source->id,
                'notes' => 'Pedido para cliente mostrador.',
                'items' => [
                    [
                        'inventory_product_id' => $product->id,
                        'requested_qty' => 2,
                    ],
                    [
                        'product_code' => 'MAN-001',
                        'product_name' => 'Item manual sin inventario cargado',
                        'unit' => 'UND',
                        'requested_qty' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.transfers.index'));

        $transfer = BranchTransfer::query()->with('items')->firstOrFail();

        $this->assertSame($source->id, $transfer->source_branch_id);
        $this->assertSame($destination->id, $transfer->destination_branch_id);
        $this->assertSame(BranchTransfer::STATUS_REQUESTED, $transfer->status);
        $this->assertCount(2, $transfer->items);
        $this->assertTrue((bool) $transfer->items->firstWhere('product_code', 'TAL-001')->source_stock_verified);
        $this->assertFalse((bool) $transfer->items->firstWhere('product_code', 'MAN-001')->source_stock_verified);
        $this->assertSame(1, BranchTransferEvent::query()->where('type', 'requested')->count());
    }

    public function test_transfer_request_creation_is_idempotent_by_request_key(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $product = $this->product($source, 'TAL-001', 'Taladro percutor');
        $payload = [
            'idempotency_key' => 'retry-transfer-1',
            'source_branch_id' => $source->id,
            'notes' => 'Pedido para cliente mostrador.',
            'items' => [[
                'inventory_product_id' => $product->id,
                'requested_qty' => 2,
            ]],
        ];

        $this->actingAs($seller)
            ->post(route('inventory.transfers.store'), $payload)
            ->assertRedirect(route('inventory.transfers.index'));

        $this->actingAs($seller)
            ->post(route('inventory.transfers.store'), $payload)
            ->assertRedirect(route('inventory.transfers.index'));

        $this->assertSame(1, BranchTransfer::query()->count());
        $this->assertSame(1, BranchTransferEvent::query()->where('type', 'requested')->count());
    }

    public function test_source_warehouse_prepares_and_ships_transfer(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $warehouse = $this->userForBranch($source, 'Bodeguero');
        $transfer = $this->createTransfer($seller, $source, $destination);

        $this->actingAs($warehouse)
            ->post(route('inventory.transfers.start-preparing', $transfer->id), [
                'notes' => 'Separado en percha.',
                'items' => $transfer->items->map(fn ($item) => [
                    'id' => $item->id,
                    'prepared_qty' => (float) $item->requested_qty,
                    'preparation_notes' => null,
                ])->all(),
            ])
            ->assertRedirect();

        $this->assertSame(BranchTransfer::STATUS_PREPARING, $transfer->refresh()->status);

        $this->actingAs($warehouse)
            ->post(route('inventory.transfers.ready-to-ship', $transfer->id))
            ->assertRedirect();

        $this->assertSame(BranchTransfer::STATUS_READY_TO_SHIP, $transfer->refresh()->status);

        $this->actingAs($warehouse)
            ->post(route('inventory.transfers.ship', $transfer->id), [
                'notes' => 'Sale en camion de la tarde.',
            ])
            ->assertRedirect();

        $this->assertSame(BranchTransfer::STATUS_IN_TRANSIT, $transfer->refresh()->status);
        $this->assertNotNull($transfer->shipped_at);
    }

    public function test_destination_warehouse_receives_with_discrepancy(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $sourceWarehouse = $this->userForBranch($source, 'Bodeguero');
        $destinationWarehouse = $this->userForBranch($destination, 'Bodeguero');
        $transfer = $this->createTransfer($seller, $source, $destination);
        $this->prepareAndShip($transfer, $sourceWarehouse);
        $firstItem = $transfer->refresh()->items()->firstOrFail();

        $this->actingAs($destinationWarehouse)
            ->post(route('inventory.transfers.receive', $transfer->id), [
                'notes' => 'Falto una unidad.',
                'items' => $transfer->items->map(fn ($item) => [
                    'id' => $item->id,
                    'received_qty' => $item->is($firstItem)
                        ? max(0, ((float) $item->prepared_qty) - 1)
                        : (float) $item->prepared_qty,
                    'reception_notes' => $item->is($firstItem) ? 'Diferencia fisica' : null,
                ])->all(),
            ])
            ->assertRedirect();

        $this->assertSame(BranchTransfer::STATUS_RECEIVED_DISCREPANCY, $transfer->refresh()->status);
        $this->assertTrue((bool) $firstItem->refresh()->has_discrepancy);
        $this->assertSame(1, BranchTransferEvent::query()->where('type', 'received_discrepancy')->count());
    }

    public function test_unassigned_user_cannot_operate_transfer(): void
    {
        [$destination, $source, $other] = Branch::factory()->count(3)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $outsider = $this->userForBranch($other, 'Bodeguero');
        $transfer = $this->createTransfer($seller, $source, $destination);

        $this->actingAs($outsider)
            ->post(route('inventory.transfers.start-preparing', $transfer->id), [
                'items' => $transfer->items->map(fn ($item) => [
                    'id' => $item->id,
                    'prepared_qty' => (float) $item->requested_qty,
                ])->all(),
            ])
            ->assertForbidden();
    }

    public function test_owner_can_view_all_transfers(): void
    {
        [$destination, $source, $ownerBranch] = Branch::factory()->count(3)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $owner = $this->owner($ownerBranch);

        $this->createTransfer($seller, $source, $destination);

        $this->actingAs($owner)
            ->get(route('inventory.transfers.index'))
            ->assertOk();
    }

    public function test_tini_completion_does_not_change_inventory_stock(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $sourceWarehouse = $this->userForBranch($source, 'Bodeguero');
        $inventoryLead = $this->userForBranch($destination, 'Encargado Inventario');
        $product = $this->product($source, 'ROT-001', 'Rotomartillo', 8);
        $transfer = $this->createTransfer($seller, $source, $destination, $product);
        $this->prepareAndShip($transfer, $sourceWarehouse);
        $this->receiveAll($transfer, $inventoryLead);

        $this->actingAs($inventoryLead)
            ->post(route('inventory.transfers.complete-tini', $transfer->id))
            ->assertRedirect();

        $this->assertSame(BranchTransfer::STATUS_TINI_COMPLETED, $transfer->refresh()->status);
        $this->assertSame('8.000', $product->refresh()->current_stock);
    }

    public function test_invalid_transition_is_rejected_by_service(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $transfer = $this->createTransfer($seller, $source, $destination);

        $this->expectException(ValidationException::class);

        app(BranchTransferService::class)->ship($transfer, $seller);
    }

    public function test_product_suggestions_prioritize_exact_code_and_name_matches(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $exact = $this->product($source, 'CEM-001', 'Cemento gris');
        $this->product($source, 'CEM-002', 'Cemento blanco');
        $this->product($source, 'ABC-001', 'Abrazadera cemento');

        $this->actingAs($seller)
            ->get(route('inventory.transfers.index', [
                'source_branch_id' => $source->id,
                'product_search' => 'cem',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productOptions.0.code', 'CEM-002')
                ->where('productOptions.1.code', 'CEM-001')
            );

        $this->actingAs($seller)
            ->get(route('inventory.transfers.index', [
                'source_branch_id' => $source->id,
                'product_search' => 'CEM-001',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productOptions.0.id', $exact->id)
            );
    }

    public function test_product_suggestions_show_stock_by_branch_and_recommend_highest_stock(): void
    {
        [$destination, $lowStockBranch, $highStockBranch] = Branch::factory()->count(3)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $lowStock = $this->product($lowStockBranch, 'PVC-001', 'Tubo PVC 1/2', 4);
        $highStock = $this->product($highStockBranch, 'PVC-001', 'Tubo PVC 1/2', 35);
        $this->product($destination, 'PVC-001', 'Tubo PVC 1/2', 99);

        $this->actingAs($seller)
            ->get(route('inventory.transfers.index', [
                'product_search' => 'PVC-001',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('productOptions.0.id', $highStock->id)
                ->where('productOptions.0.branch.id', $highStockBranch->id)
                ->where('productOptions.0.current_stock', 35)
                ->where('productOptions.0.isRecommended', true)
                ->where('productOptions.1.id', $lowStock->id)
                ->where('productOptions.1.branch.id', $lowStockBranch->id)
            );
    }

    public function test_user_without_create_permission_cannot_search_cross_branch_products(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $warehouse = $this->userForBranch($destination, 'Bodeguero');
        $this->product($source, 'PVC-999', 'Tubo reservado', 50);

        $this->actingAs($warehouse)
            ->get(route('inventory.transfers.index', ['product_search' => 'PVC']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('branches', [])
                ->where('productOptions', [])
            );
    }

    public function test_request_with_items_from_multiple_source_branches_creates_one_transfer_per_source(): void
    {
        [$destination, $sourceOne, $sourceTwo] = Branch::factory()->count(3)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $productOne = $this->product($sourceOne, 'BRO-001', 'Broca acero', 7);
        $productTwo = $this->product($sourceTwo, 'DIS-001', 'Disco corte', 11);

        $this->actingAs($seller)
            ->post(route('inventory.transfers.store'), [
                'notes' => 'Pedido mixto por disponibilidad.',
                'items' => [
                    [
                        'inventory_product_id' => $productOne->id,
                        'source_branch_id' => $sourceOne->id,
                        'requested_qty' => 2,
                    ],
                    [
                        'inventory_product_id' => $productTwo->id,
                        'source_branch_id' => $sourceTwo->id,
                        'requested_qty' => 3,
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.transfers.index'));

        $this->assertSame(2, BranchTransfer::query()->count());
        $this->assertDatabaseHas('pherce_intel.branch_transfers', [
            'source_branch_id' => $sourceOne->id,
            'destination_branch_id' => $destination->id,
        ]);
        $this->assertDatabaseHas('pherce_intel.branch_transfers', [
            'source_branch_id' => $sourceTwo->id,
            'destination_branch_id' => $destination->id,
        ]);
    }

    public function test_warehouse_receipt_screen_shows_inbound_and_outbound_transfer_tasks(): void
    {
        [$destination, $source] = Branch::factory()->count(2)->create();
        $seller = $this->userForBranch($destination, 'Vendedor');
        $sourceWarehouse = $this->userForBranch($source, 'Bodeguero');
        $destinationWarehouse = $this->userForBranch($destination, 'Bodeguero');

        $outbound = $this->createTransfer($seller, $source, $destination);

        $this->actingAs($sourceWarehouse)
            ->get(route('purchasing.receipt.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewMode', 'warehouse')
                ->where('transferTasks.counts.outbound', 1)
                ->where('transferTasks.outbound.0.id', $outbound->id)
                ->where('transferTasks.outbound.0.status', BranchTransfer::STATUS_REQUESTED)
                ->where('transferTasks.counts.inbound', 0)
            );

        $this->prepareAndShip($outbound, $sourceWarehouse);

        $this->actingAs($destinationWarehouse)
            ->get(route('purchasing.receipt.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewMode', 'warehouse')
                ->where('transferTasks.counts.inbound', 1)
                ->where('transferTasks.inbound.0.id', $outbound->id)
                ->where('transferTasks.inbound.0.status', BranchTransfer::STATUS_IN_TRANSIT)
                ->where('transferTasks.counts.outbound', 0)
            );
    }

    private function createTransfer(User $requester, Branch $source, Branch $destination, ?InventoryProduct $product = null): BranchTransfer
    {
        $product ??= $this->product($source);

        return app(BranchTransferService::class)->createRequest(
            sourceBranchId: $source->id,
            destinationBranchId: $destination->id,
            items: [[
                'inventory_product_id' => $product->id,
                'requested_qty' => 2,
            ]],
            user: $requester,
        );
    }

    private function prepareAndShip(BranchTransfer $transfer, User $warehouse): void
    {
        app(BranchTransferService::class)->startPreparing(
            transfer: $transfer,
            user: $warehouse,
            items: $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'prepared_qty' => (float) $item->requested_qty,
            ])->all(),
        );

        app(BranchTransferService::class)->markReadyToShip($transfer->refresh(), $warehouse);
        app(BranchTransferService::class)->ship($transfer->refresh(), $warehouse);
    }

    private function receiveAll(BranchTransfer $transfer, User $warehouse): void
    {
        app(BranchTransferService::class)->receive(
            transfer: $transfer->refresh(),
            user: $warehouse,
            items: $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'received_qty' => (float) $item->prepared_qty,
            ])->all(),
        );
    }

    private function product(Branch $branch, string $code = 'PROD-001', string $name = 'Producto prueba', int $stock = 10): InventoryProduct
    {
        return InventoryProduct::create([
            'branch_id' => $branch->id,
            'code' => $code,
            'name' => $name,
            'unit' => 'UND',
            'current_stock' => $stock,
        ]);
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

    private function owner(Branch $branch): User
    {
        Permission::firstOrCreate([
            'name' => 'branches.view-all',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate(['name' => config('internal.owner_roles.0'), 'guard_name' => 'web']);
        $role->givePermissionTo('branches.view-all');

        $user = User::factory()->create([
            'active_branch_id' => $branch->id,
        ]);
        $user->branches()->syncWithoutDetaching([$branch->id]);
        $user->assignRole($role);

        return $user;
    }
}
