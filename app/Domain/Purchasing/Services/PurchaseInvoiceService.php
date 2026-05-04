<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\DTOs\PurchaseInvoiceData;
use App\Domain\Purchasing\Events\InvoiceEmailReceived;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Purchasing\Models\ReceptionConfirmation;
use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;

final class PurchaseInvoiceService
{
    /**
     * Crea una PurchaseInvoice a partir de un email de Gmail.
     * Retorna null si el mensaje ya fue procesado (idempotencia por gmail_message_id).
     */
    public function createFromGmailMessage(PurchaseInvoiceData $data, int $branchId): ?PurchaseInvoice
    {
        if (PurchaseInvoice::withoutBranchScope()->where('gmail_message_id', $data->gmailMessageId)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($data, $branchId): PurchaseInvoice {
            Context::addHidden('branch_scope_bypass', true);

            $supplier = Supplier::withoutBranchScope()->firstOrCreate(
                ['branch_id' => $branchId, 'ruc' => $data->supplierRuc],
                ['name' => $data->supplierName, 'email' => $data->fromEmail],
            );

            Context::forgetHidden('branch_scope_bypass');

            $invoice = PurchaseInvoice::create([
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'invoice_number' => $data->invoiceNumber,
                'access_key' => $data->accessKey,
                'emission_date' => $data->emissionDate,
                'total' => $data->total,
                'status' => 'pending',
                'gmail_message_id' => $data->gmailMessageId,
                'from_email' => $data->fromEmail,
            ]);

            $rows = array_map(fn ($item) => [
                'code' => $item->code,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unitPrice,
                'subtotal' => $item->subtotal,
            ], $data->items);

            $invoice->items()->createMany($rows);

            ReceptionConfirmation::create([
                'branch_id' => $branchId,
                'invoice_id' => $invoice->id,
                'status' => 'pending',
            ]);

            event(new InvoiceEmailReceived($invoice));

            return $invoice;
        });
    }
}
