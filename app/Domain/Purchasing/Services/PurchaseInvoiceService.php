<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\DTOs\PurchaseInvoiceData;
use App\Domain\Purchasing\Events\InvoiceEmailReceived;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Purchasing\Models\ReceptionConfirmation;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PurchaseInvoiceService
{
    public function __construct(
        private readonly ReceptionWorkflowService $workflow,
    ) {}

    /**
     * Crea una PurchaseInvoice a partir de un email de Gmail.
     * Retorna null si el mensaje ya fue procesado (idempotencia por gmail_message_id).
     */
    public function createFromGmailMessage(PurchaseInvoiceData $data, int $branchId): ?PurchaseInvoice
    {
        if (PurchaseInvoice::withoutBranchScope()->where('gmail_message_id', $data->gmailMessageId)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($data, $branchId): ?PurchaseInvoice {
            $existing = PurchaseInvoice::withoutBranchScope()
                ->where('access_key', $data->accessKey)
                ->first();

            if ($existing instanceof PurchaseInvoice) {
                $this->ensureReceptionForInvoice($existing);

                return null;
            }

            $supplier = $this->findOrCreateSupplier($data, $branchId);

            try {
                $invoice = PurchaseInvoice::create([
                    'branch_id' => $branchId,
                    'supplier_id' => $supplier->id,
                    'invoice_number' => $data->invoiceNumber,
                    'access_key' => $data->accessKey,
                    'emission_date' => $data->emissionDate,
                    'total' => $data->total,
                    'status' => 'awaiting_physical',
                    'gmail_message_id' => $data->gmailMessageId,
                    'from_email' => $data->fromEmail,
                ]);
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '23505') {
                    throw $exception;
                }

                $existing = PurchaseInvoice::withoutBranchScope()
                    ->where('access_key', $data->accessKey)
                    ->orWhere('gmail_message_id', $data->gmailMessageId)
                    ->first();

                if ($existing instanceof PurchaseInvoice) {
                    $this->ensureReceptionForInvoice($existing);

                    return null;
                }

                throw $exception;
            }

            $rows = array_map(fn ($item) => [
                'code' => $item->code,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
                'subtotal' => $item->subtotal,
            ], $data->items);

            $invoice->items()->createMany($rows);

            $confirmation = ReceptionConfirmation::create([
                'branch_id' => $branchId,
                'invoice_id' => $invoice->id,
                'status' => 'pending',
            ]);
            $this->workflow->ensureReceptionItems($confirmation);
            $this->workflow->recordEvent(
                invoice: $invoice,
                confirmation: $confirmation,
                type: 'invoice_detected',
                title: 'Factura detectada desde Gmail',
                body: 'El XML fue procesado y quedo a la espera de recepcion fisica.',
                metadata: [
                    'gmail_message_id' => $data->gmailMessageId,
                    'access_key' => $data->accessKey,
                ],
            );
            $this->workflow->recordEvent(
                invoice: $invoice,
                confirmation: $confirmation,
                type: 'warehouse_notified',
                title: 'Recepcion pendiente para bodega',
                body: 'Bodega puede validar la mercaderia desde la vista movil.',
            );

            event(new InvoiceEmailReceived($invoice));

            return $invoice;
        });
    }

    private function ensureReceptionForInvoice(PurchaseInvoice $invoice): ReceptionConfirmation
    {
        if ($invoice->status === 'pending') {
            $invoice->update(['status' => 'awaiting_physical']);
        }

        $confirmation = ReceptionConfirmation::withoutBranchScope()->firstOrCreate(
            ['invoice_id' => $invoice->id],
            [
                'branch_id' => $invoice->branch_id,
                'status' => 'pending',
            ],
        );
        $this->workflow->ensureReceptionItems($confirmation);

        return $confirmation;
    }

    private function findOrCreateSupplier(PurchaseInvoiceData $data, int $branchId): Supplier
    {
        Context::addHidden('branch_scope_bypass', true);

        try {
            return Supplier::withoutBranchScope()->firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'ruc_hash' => $this->supplierRucHash($data->supplierRuc),
                ],
                [
                    'ruc' => $data->supplierRuc,
                    'name' => $data->supplierName,
                    'email' => $data->fromEmail,
                ],
            );
        } finally {
            Context::forgetHidden('branch_scope_bypass');
        }
    }

    private function supplierRucHash(string $ruc): string
    {
        return hash('sha256', Str::lower(preg_replace('/\D+/', '', $ruc) ?? $ruc));
    }
}
