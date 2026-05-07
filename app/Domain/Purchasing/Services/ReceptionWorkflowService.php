<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Purchasing\Models\PurchaseInvoiceEvent;
use App\Domain\Purchasing\Models\ReceptionConfirmation;
use App\Domain\Purchasing\Models\ReceptionConfirmationItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReceptionWorkflowService
{
    /**
     * @param  array<int, array{id:int, received_qty:numeric-string|float|int, condition_status:string, discrepancy_notes?:string|null}>  $items
     */
    public function confirmReception(ReceptionConfirmation $confirmation, array $items, int $userId, ?string $notes = null): ReceptionConfirmation
    {
        if (in_array($confirmation->status, ['confirmed', 'discrepancy'], true)) {
            return $confirmation->load(['invoice.supplier', 'items', 'events']);
        }

        return DB::transaction(function () use ($confirmation, $items, $userId, $notes): ReceptionConfirmation {
            $confirmation->loadMissing('invoice', 'items');
            $itemsById = collect($items)->keyBy('id');
            $submittedItemIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id);
            $validItemIds = $confirmation->items->pluck('id')->map(fn ($id) => (int) $id);
            $hasDiscrepancy = false;

            if (
                $submittedItemIds->unique()->count() !== $submittedItemIds->count()
                || $submittedItemIds->sort()->values()->all() !== $validItemIds->sort()->values()->all()
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Debes confirmar todos los items de esta recepcion.',
                ]);
            }

            foreach ($confirmation->items as $item) {
                $payload = $itemsById->get($item->id);

                if ($payload === null) {
                    continue;
                }

                $receivedQty = (float) $payload['received_qty'];
                $conditionStatus = $this->normalizeConditionStatus((string) $payload['condition_status']);
                $lineHasDiscrepancy = $this->lineHasDiscrepancy($item, $receivedQty, $conditionStatus);
                $hasDiscrepancy = $hasDiscrepancy || $lineHasDiscrepancy;

                $item->update([
                    'received_qty' => $receivedQty,
                    'condition_status' => $conditionStatus,
                    'has_discrepancy' => $lineHasDiscrepancy,
                    'discrepancy_notes' => $payload['discrepancy_notes'] ?? null,
                ]);

                if ($lineHasDiscrepancy) {
                    $this->recordEvent(
                        invoice: $confirmation->invoice,
                        confirmation: $confirmation,
                        type: 'item_discrepancy',
                        title: 'Novedad en item recibido',
                        body: $item->description,
                        userId: $userId,
                        metadata: [
                            'item_id' => $item->id,
                            'expected_qty' => (float) $item->expected_qty,
                            'received_qty' => $receivedQty,
                            'condition_status' => $conditionStatus,
                        ],
                        once: false,
                    );
                }
            }

            $confirmation->update([
                'status' => $hasDiscrepancy ? 'discrepancy' : 'confirmed',
                'confirmed_by' => $userId,
                'notes' => $notes,
                'confirmed_at' => Carbon::now(),
            ]);

            $confirmation->invoice->update([
                'status' => $hasDiscrepancy ? 'received_discrepancy' : 'received_ok',
            ]);

            $this->recordEvent(
                invoice: $confirmation->invoice,
                confirmation: $confirmation,
                type: $hasDiscrepancy ? 'reception_confirmed_with_discrepancy' : 'reception_confirmed_ok',
                title: $hasDiscrepancy ? 'Recepcion confirmada con novedad' : 'Recepcion confirmada conforme',
                body: $hasDiscrepancy
                    ? 'Bodega confirmo diferencias fisicas que requieren revision de compras.'
                    : 'Bodega confirmo que la mercaderia coincide con la factura.',
                userId: $userId,
                metadata: ['notes' => $notes],
            );

            return $confirmation->refresh()->load(['invoice.supplier', 'items', 'events']);
        });
    }

    public function startReception(ReceptionConfirmation $confirmation, int $userId): ReceptionConfirmation
    {
        if ($confirmation->status !== 'pending') {
            return $confirmation->load(['invoice.supplier', 'items', 'events']);
        }

        return DB::transaction(function () use ($confirmation, $userId): ReceptionConfirmation {
            $confirmation->loadMissing('invoice');
            $confirmation->update(['status' => 'receiving']);
            $confirmation->invoice->update(['status' => 'receiving']);

            $this->recordEvent(
                invoice: $confirmation->invoice,
                confirmation: $confirmation,
                type: 'reception_started',
                title: 'Bodega inicio la recepcion fisica',
                body: 'La factura esta siendo validada contra la mercaderia recibida.',
                userId: $userId,
            );

            return $confirmation->refresh()->load(['invoice.supplier', 'items', 'events']);
        });
    }

    public function closeReview(PurchaseInvoice $invoice, int $userId, string $action, ?string $notes = null): PurchaseInvoice
    {
        if ($invoice->status === 'closed') {
            return $invoice->load(['supplier', 'items', 'receptionConfirmation.items', 'events.user']);
        }

        if (! in_array($invoice->status, ['received_ok', 'received_discrepancy'], true)) {
            throw ValidationException::withMessages([
                'invoice' => 'La factura debe tener recepcion fisica confirmada antes de cerrar el expediente.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $userId, $action, $notes): PurchaseInvoice {
            $invoice->loadMissing('receptionConfirmation');
            $invoice->update(['status' => 'closed']);

            $this->recordEvent(
                invoice: $invoice,
                confirmation: $invoice->receptionConfirmation,
                type: 'review_closed',
                title: $this->reviewActionTitle($action),
                body: $notes,
                userId: $userId,
                metadata: ['action' => $action],
            );

            return $invoice->refresh()->load(['supplier', 'items', 'receptionConfirmation.items', 'events.user']);
        });
    }

    public function ensureReceptionItems(ReceptionConfirmation $confirmation): void
    {
        $confirmation->loadMissing('invoice.items');

        foreach ($confirmation->invoice->items as $invoiceItem) {
            ReceptionConfirmationItem::firstOrCreate(
                [
                    'confirmation_id' => $confirmation->id,
                    'purchase_invoice_item_id' => $invoiceItem->id,
                ],
                [
                    'tini_product_id' => $invoiceItem->code,
                    'description' => $invoiceItem->description,
                    'expected_qty' => $invoiceItem->quantity,
                    'received_qty' => 0,
                    'condition_status' => 'ok',
                    'has_discrepancy' => false,
                ],
            );
        }
    }

    public function recordEvent(
        PurchaseInvoice $invoice,
        ?ReceptionConfirmation $confirmation,
        string $type,
        string $title,
        ?string $body = null,
        ?int $userId = null,
        array $metadata = [],
        bool $once = true,
    ): ?PurchaseInvoiceEvent {
        if ($once && PurchaseInvoiceEvent::withoutBranchScope()
            ->where('invoice_id', $invoice->id)
            ->where('type', $type)
            ->exists()) {
            return null;
        }

        return PurchaseInvoiceEvent::create([
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'reception_confirmation_id' => $confirmation?->id,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    private function normalizeConditionStatus(string $status): string
    {
        return in_array($status, ['ok', 'short', 'over', 'missing', 'damaged'], true)
            ? $status
            : 'ok';
    }

    private function lineHasDiscrepancy(ReceptionConfirmationItem $item, float $receivedQty, string $conditionStatus): bool
    {
        return abs(((float) $item->expected_qty) - $receivedQty) > 0.0001
            || $conditionStatus !== 'ok';
    }

    private function reviewActionTitle(string $action): string
    {
        return match ($action) {
            'supplier_contacted' => 'Proveedor contactado',
            'credit_note_requested' => 'Nota de credito solicitada',
            default => 'Novedad cerrada',
        };
    }
}
