<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Purchasing\Models\PurchaseInvoiceEvent;
use App\Domain\Purchasing\Models\PurchaseInvoiceItem;
use App\Domain\Purchasing\Models\ReceptionConfirmation;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Services\ReceptionWorkflowService;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class PurchasingReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_warehouse_user_can_see_start_and_confirm_invoice_reception_with_discrepancy(): void
    {
        $branch = Branch::factory()->create();
        $warehouse = $this->userForBranch($branch, 'Bodeguero');
        [$invoice, $confirmation] = $this->invoiceAwaitingReception($branch);

        $this->actingAs($warehouse)
            ->get(route('purchasing.receipt.index', ['invoice' => $invoice->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewMode', 'warehouse')
                ->where('invoices.0.id', $invoice->id)
                ->where('selectedInvoice.id', $invoice->id)
                ->where('selectedInvoice.confirmation.status', 'pending')
                ->where('selectedInvoice.confirmation.items.0.description', 'Cemento gris 50kg')
            );

        $this->actingAs($warehouse)
            ->post(route('purchasing.receipt.start', $confirmation->id))
            ->assertRedirect(route('purchasing.receipt.index', ['invoice' => $invoice->id]));

        $this->assertSame('receiving', $invoice->refresh()->status);
        $this->assertSame('receiving', $confirmation->refresh()->status);

        $items = $confirmation->items()->orderBy('id')->get();

        $this->actingAs($warehouse)
            ->post(route('purchasing.receipt.confirm', $confirmation->id), [
                'notes' => 'Una funda llego rota.',
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'received_qty' => $item->description === 'Cemento gris 50kg' ? 9 : (float) $item->expected_qty,
                    'condition_status' => $item->description === 'Cemento gris 50kg' ? 'damaged' : 'ok',
                    'discrepancy_notes' => $item->description === 'Cemento gris 50kg' ? 'Empaque roto' : null,
                ])->all(),
            ])
            ->assertRedirect(route('purchasing.receipt.index'));

        $this->assertSame('received_discrepancy', $invoice->refresh()->status);
        $this->assertSame('discrepancy', $confirmation->refresh()->status);
        $this->assertTrue((bool) $confirmation->items()->where('description', 'Cemento gris 50kg')->firstOrFail()->has_discrepancy);
        $this->assertDatabaseHas('pherce_intel.purchase_invoice_events', [
            'invoice_id' => $invoice->id,
            'reception_confirmation_id' => $confirmation->id,
            'type' => 'reception_confirmed_with_discrepancy',
        ]);
    }

    public function test_warehouse_user_from_another_branch_cannot_operate_invoice_reception(): void
    {
        [$invoiceBranch, $otherBranch] = Branch::factory()->count(2)->create();
        $outsider = $this->userForBranch($otherBranch, 'Bodeguero');
        [, $confirmation] = $this->invoiceAwaitingReception($invoiceBranch);

        $this->actingAs($outsider)
            ->post(route('purchasing.receipt.start', $confirmation->id))
            ->assertForbidden();
    }

    public function test_reception_requires_every_invoice_item_once(): void
    {
        $branch = Branch::factory()->create();
        $warehouse = $this->userForBranch($branch, 'Bodeguero');
        [, $confirmation] = $this->invoiceAwaitingReception($branch);
        $items = $confirmation->items()->orderBy('id')->get();

        $this->actingAs($warehouse)
            ->post(route('purchasing.receipt.confirm', $confirmation->id), [
                'items' => [
                    [
                        'id' => $items->first()->id,
                        'received_qty' => 10,
                        'condition_status' => 'ok',
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');
    }

    /**
     * @return array{0: PurchaseInvoice, 1: ReceptionConfirmation}
     */
    private function invoiceAwaitingReception(Branch $branch): array
    {
        $supplierCode = '1790012345001';
        $supplierName = 'Proveedor Demo S.A.';

        InventoryProduct::create([
            'branch_id' => $branch->id,
            'code' => 'CEM-001',
            'name' => 'Cemento gris 50kg',
            'unit' => 'UND',
            'current_stock' => 4,
            'supplier_code' => $supplierCode,
            'supplier_name' => $supplierName,
        ]);

        $supplier = Supplier::create([
            'branch_id' => $branch->id,
            'ruc' => $supplierCode,
            'ruc_hash' => hash('sha256', $supplierCode),
            'name' => $supplierName,
            'email' => 'proveedor@example.test',
            'is_active' => true,
        ]);

        $invoice = PurchaseInvoice::create([
            'branch_id' => $branch->id,
            'supplier_id' => $supplier->id,
            'invoice_number' => '001-001-000000123',
            'access_key' => '0705202601179001234500120010010000001230000001211',
            'emission_date' => now()->toDateString(),
            'total' => 123.45,
            'status' => 'awaiting_physical',
            'gmail_message_id' => 'gmail-demo-'.$branch->id,
            'from_email' => 'proveedor@example.test',
        ]);

        PurchaseInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'code' => 'CEM-001',
            'description' => 'Cemento gris 50kg',
            'quantity' => 10,
            'unit_price' => 7.50,
            'subtotal' => 75,
        ]);

        PurchaseInvoiceItem::create([
            'invoice_id' => $invoice->id,
            'code' => 'BRO-001',
            'description' => 'Broca acero',
            'quantity' => 3,
            'unit_price' => 4.50,
            'subtotal' => 13.50,
        ]);

        $confirmation = ReceptionConfirmation::create([
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'status' => 'pending',
        ]);

        app(ReceptionWorkflowService::class)->ensureReceptionItems($confirmation);

        PurchaseInvoiceEvent::create([
            'branch_id' => $branch->id,
            'invoice_id' => $invoice->id,
            'reception_confirmation_id' => $confirmation->id,
            'type' => 'invoice_detected',
            'title' => 'Factura detectada desde Gmail',
        ]);

        return [$invoice, $confirmation->refresh()];
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
}
