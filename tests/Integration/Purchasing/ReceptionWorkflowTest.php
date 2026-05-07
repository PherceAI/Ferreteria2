<?php

declare(strict_types=1);

namespace Tests\Integration\Purchasing;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\DTOs\InvoiceLineItemData;
use App\Domain\Purchasing\DTOs\PurchaseInvoiceData;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Purchasing\Models\PurchaseInvoiceEvent;
use App\Domain\Purchasing\Models\ReceptionConfirmation;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Services\PurchaseInvoiceService;
use App\Domain\Purchasing\Services\ReceptionWorkflowService;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class ReceptionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_gmail_ingestion_is_idempotent_and_creates_reception_task(): void
    {
        $branch = $this->branch();
        $data = $this->invoiceData();
        $service = app(PurchaseInvoiceService::class);

        $invoice = $service->createFromGmailMessage($data, $branch->id);
        $duplicate = $service->createFromGmailMessage($data, $branch->id);

        $this->assertInstanceOf(PurchaseInvoice::class, $invoice);
        $this->assertNull($duplicate);
        $this->assertSame(1, PurchaseInvoice::withoutBranchScope()->where('access_key', $data->accessKey)->count());
        $this->assertSame('awaiting_physical', $invoice->status);
        $this->assertSame(1, ReceptionConfirmation::withoutBranchScope()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(2, $invoice->receptionConfirmation->items()->count());
        $this->assertSame(1, PurchaseInvoiceEvent::withoutBranchScope()->where('invoice_id', $invoice->id)->where('type', 'invoice_detected')->count());
        $this->assertSame(1, Supplier::withoutBranchScope()->where('branch_id', $branch->id)->count());
        $this->assertNotNull($invoice->supplier->ruc_hash);
    }

    public function test_confirming_reception_sets_ok_or_discrepancy_status(): void
    {
        $branch = $this->branch();
        $user = $this->user($branch);
        $invoice = app(PurchaseInvoiceService::class)->createFromGmailMessage($this->invoiceData(), $branch->id);
        $confirmation = $invoice->receptionConfirmation()->with('items')->firstOrFail();

        $confirmed = app(ReceptionWorkflowService::class)->confirmReception(
            confirmation: $confirmation,
            items: $confirmation->items->map(fn ($item) => [
                'id' => $item->id,
                'received_qty' => (float) $item->expected_qty,
                'condition_status' => 'ok',
                'discrepancy_notes' => null,
            ])->all(),
            userId: $user->id,
        );

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('received_ok', $confirmed->invoice->refresh()->status);

        $invoiceWithDifference = app(PurchaseInvoiceService::class)->createFromGmailMessage($this->invoiceData('B'), $branch->id);
        $differenceConfirmation = $invoiceWithDifference->receptionConfirmation()->with('items')->firstOrFail();
        $firstItem = $differenceConfirmation->items->first();

        $confirmedWithDifference = app(ReceptionWorkflowService::class)->confirmReception(
            confirmation: $differenceConfirmation,
            items: $differenceConfirmation->items->map(fn ($item) => [
                'id' => $item->id,
                'received_qty' => $item->is($firstItem)
                    ? max(0, ((float) $item->expected_qty) - 1)
                    : (float) $item->expected_qty,
                'condition_status' => $item->is($firstItem) ? 'short' : 'ok',
                'discrepancy_notes' => $item->is($firstItem) ? 'Falto una unidad' : null,
            ])->all(),
            userId: $user->id,
        );

        $this->assertSame('discrepancy', $confirmedWithDifference->status);
        $this->assertSame('received_discrepancy', $confirmedWithDifference->invoice->refresh()->status);
        $this->assertTrue((bool) $confirmedWithDifference->items()->whereKey($firstItem->id)->first()->has_discrepancy);
    }

    public function test_reception_requires_every_item_to_be_confirmed(): void
    {
        $branch = $this->branch();
        $user = $this->user($branch);
        $invoice = app(PurchaseInvoiceService::class)->createFromGmailMessage($this->invoiceData(), $branch->id);
        $confirmation = $invoice->receptionConfirmation()->with('items')->firstOrFail();
        $firstItem = $confirmation->items->first();

        $this->expectException(ValidationException::class);

        app(ReceptionWorkflowService::class)->confirmReception(
            confirmation: $confirmation,
            items: [[
                'id' => $firstItem->id,
                'received_qty' => (float) $firstItem->expected_qty,
                'condition_status' => 'ok',
                'discrepancy_notes' => null,
            ]],
            userId: $user->id,
        );
    }

    public function test_seller_cannot_view_physical_reception_inbox(): void
    {
        $branch = $this->branch();
        $user = User::factory()->create(['active_branch_id' => $branch->id]);
        $user->branches()->attach($branch->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']));

        $this->actingAs($user)
            ->get(route('purchasing.receipt.index'))
            ->assertForbidden();
    }

    public function test_receipt_inbox_hides_existing_invoices_from_suppliers_not_in_inventory(): void
    {
        $branch = $this->branch();
        $user = $this->user($branch);
        $user->assignRole(Role::firstOrCreate(['name' => 'Encargada Compras', 'guard_name' => 'web']));

        InventoryProduct::create([
            'branch_id' => $branch->id,
            'code' => 'FER-001',
            'name' => 'Producto de ferreteria',
            'current_stock' => 1,
            'supplier_code' => '0999999999001',
            'supplier_name' => 'Proveedor Test',
        ]);

        $knownInvoice = app(PurchaseInvoiceService::class)->createFromGmailMessage($this->invoiceData('K'), $branch->id);
        app(PurchaseInvoiceService::class)->createFromGmailMessage(
            new PurchaseInvoiceData(
                gmailMessageId: 'gas-message-'.random_int(1000, 9999),
                fromEmail: 'gasolinera@example.com',
                supplierRuc: '0600000000001',
                supplierName: 'ESTACION DE SERVICIO Y GASOLINERA LOS ALTARES CIA LTDA',
                invoiceNumber: '001-007-000212953',
                accessKey: str_pad('GAS'.random_int(1000, 9999), 49, '0'),
                emissionDate: now()->toDateString(),
                total: 48.16,
                items: [
                    new InvoiceLineItemData('0121', 'DIESEL PREMIUM', 17.84, 2.70, 48.16),
                ],
            ),
            $branch->id,
        );

        $this->actingAs($user)
            ->get(route('purchasing.receipt.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('purchasing/receipt/index')
                ->has('invoices', 1)
                ->where('invoices.0.id', $knownInvoice->id)
                ->where('invoices.0.supplier', 'Proveedor Test')
                ->where('stats.awaitingPhysical', 1));
    }

    public function test_review_cannot_close_before_physical_reception(): void
    {
        $branch = $this->branch();
        $user = $this->user($branch);
        $invoice = app(PurchaseInvoiceService::class)->createFromGmailMessage($this->invoiceData(), $branch->id);

        $this->expectException(ValidationException::class);

        app(ReceptionWorkflowService::class)->closeReview(
            invoice: $invoice,
            userId: $user->id,
            action: 'closed',
        );
    }

    public function test_warehouse_can_start_and_confirm_reception_through_routes(): void
    {
        $branch = $this->branch();
        $user = $this->user($branch);
        $user->assignRole(Role::firstOrCreate(['name' => 'Bodeguero', 'guard_name' => 'web']));

        $invoice = app(PurchaseInvoiceService::class)->createFromGmailMessage($this->invoiceData(), $branch->id);
        $confirmation = $invoice->receptionConfirmation()->with('items')->firstOrFail();

        $response = $this->actingAs($user)
            ->post(route('purchasing.receipt.start', $confirmation->id));

        $response->assertStatus(302);
        $this->assertSame(
            route('purchasing.receipt.index', ['invoice' => $invoice->id]),
            $response->headers->get('Location'),
        );

        $this->assertSame('receiving', $confirmation->refresh()->status);

        $this->actingAs($user)
            ->post(route('purchasing.receipt.confirm', $confirmation->id), [
                'notes' => null,
                'items' => $confirmation->items->map(fn ($item) => [
                    'id' => $item->id,
                    'received_qty' => (float) $item->expected_qty,
                    'condition_status' => 'ok',
                    'discrepancy_notes' => null,
                ])->all(),
            ])
            ->assertRedirect(route('purchasing.receipt.index'));

        $this->assertSame('received_ok', $invoice->refresh()->status);
    }

    private function branch(): Branch
    {
        return Branch::query()->first()
            ?? Branch::create([
                'name' => 'Riobamba Matriz',
                'code' => 'RIO1',
                'city' => 'Riobamba',
            ]);
    }

    private function user(Branch $branch): User
    {
        $user = User::factory()->create([
            'active_branch_id' => $branch->id,
        ]);

        $user->branches()->syncWithoutDetaching([$branch->id]);

        return $user;
    }

    private function invoiceData(string $suffix = 'A'): PurchaseInvoiceData
    {
        $unique = str_pad((string) random_int(1, 999999999999), 12, '0', STR_PAD_LEFT);

        return new PurchaseInvoiceData(
            gmailMessageId: "gmail-test-{$suffix}-{$unique}",
            fromEmail: 'proveedor@example.com',
            supplierRuc: '0999999999001',
            supplierName: 'Proveedor Test',
            invoiceNumber: "001-001-{$unique}",
            accessKey: str_pad($suffix.$unique, 49, $suffix),
            emissionDate: now()->toDateString(),
            total: 32.50,
            items: [
                new InvoiceLineItemData('COD-1', 'Producto 1', 10, 2, 20),
                new InvoiceLineItemData('COD-2', 'Producto 2', 5, 2.5, 12.5),
            ],
        );
    }
}
