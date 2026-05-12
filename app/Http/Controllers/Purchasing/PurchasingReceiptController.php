<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Purchasing\Models\ReceptionConfirmation;
use App\Domain\Purchasing\Services\ReceptionWorkflowService;
use App\Domain\Purchasing\Services\SupplierDocumentFilterService;
use App\Domain\Warehouse\Models\BranchTransfer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class PurchasingReceiptController extends Controller
{
    public function __construct(
        private readonly ReceptionWorkflowService $workflow,
        private readonly SupplierDocumentFilterService $supplierFilter,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($this->canViewReceipts($request), 403);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'awaiting_physical', 'receiving', 'received_ok', 'received_discrepancy', 'closed'])],
            'search' => ['nullable', 'string', 'max:120'],
            'invoice' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = (string) ($validated['status'] ?? '');
        $search = trim((string) ($validated['search'] ?? ''));
        $viewMode = $this->receiptViewMode($request);
        $branchId = (int) Context::get('branch_id');
        $allowedSupplierIds = $this->supplierFilter->allowedSupplierIds($branchId);

        $query = PurchaseInvoice::query()
            ->with([
                'supplier',
                'receptionConfirmation.items',
                'events.user',
            ])
            ->withCount('items')
            ->where('branch_id', $branchId)
            ->whereIn('supplier_id', $allowedSupplierIds)
            ->latest('created_at');

        if ($viewMode === 'warehouse' && ($status === '' || $status === 'all')) {
            $query->whereIn('status', ['pending', 'awaiting_physical', 'detected', 'receiving']);
        }

        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
            });
        }

        $invoices = $query->limit(50)->get();
        $requestedInvoiceId = (int) ($validated['invoice'] ?? 0);
        $selectedInvoice = $requestedInvoiceId > 0
            ? $invoices->firstWhere('id', $requestedInvoiceId)
            : ($viewMode === 'warehouse' ? null : $invoices->first());

        if ($selectedInvoice instanceof PurchaseInvoice && $selectedInvoice->receptionConfirmation !== null) {
            $this->workflow->ensureReceptionItems($selectedInvoice->receptionConfirmation);
            $selectedInvoice->load(['supplier', 'items', 'receptionConfirmation.items', 'events.user']);
        }

        return Inertia::render('purchasing/receipt/index', [
            'filters' => [
                'status' => $status === '' ? 'all' : $status,
                'search' => $search,
            ],
            'stats' => $this->stats($branchId, $allowedSupplierIds),
            'invoices' => $invoices->map(fn (PurchaseInvoice $invoice) => $this->invoiceListData($invoice))->values(),
            'selectedInvoice' => $selectedInvoice instanceof PurchaseInvoice
                ? $this->invoiceDetailData($selectedInvoice)
                : null,
            'statusOptions' => $this->statusOptions(),
            'transferTasks' => $this->transferTasks($branchId),
            'viewMode' => $viewMode,
        ]);
    }

    public function start(int $confirmation, Request $request): RedirectResponse
    {
        abort_unless($this->canReceive($request), 403);

        $confirmation = $this->findConfirmationForUser($confirmation, $request);

        $this->workflow->startReception($confirmation, (int) $request->user()->id);

        return redirect()
            ->route('purchasing.receipt.index', ['invoice' => $confirmation->invoice_id])
            ->with('success', 'Recepcion iniciada.');
    }

    public function confirm(int $confirmation, Request $request): RedirectResponse
    {
        abort_unless($this->canReceive($request), 403);

        $confirmation = $this->findConfirmationForUser($confirmation, $request);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.condition_status' => ['required', Rule::in(['ok', 'short', 'over', 'missing', 'damaged'])],
            'items.*.discrepancy_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $submittedItemIds = collect($validated['items'])->pluck('id')->map(fn ($id) => (int) $id);
        $validItemIds = $confirmation->items()->pluck('id');

        if (
            $submittedItemIds->unique()->count() !== $submittedItemIds->count()
            || $submittedItemIds->sort()->values()->all() !== $validItemIds->sort()->values()->all()
        ) {
            throw ValidationException::withMessages([
                'items' => 'Debes confirmar todos los items de esta recepcion, sin duplicados ni items ajenos.',
            ]);
        }

        $this->workflow->confirmReception(
            confirmation: $confirmation,
            items: $validated['items'],
            userId: (int) $request->user()->id,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('purchasing.receipt.index')
            ->with('success', 'Recepcion fisica confirmada.');
    }

    public function close(int $invoice, Request $request): RedirectResponse
    {
        abort_unless($this->canManageReview($request), 403);

        $invoice = $this->findInvoiceForUser($invoice, $request);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['supplier_contacted', 'credit_note_requested', 'closed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->workflow->closeReview(
            invoice: $invoice,
            userId: (int) $request->user()->id,
            action: $validated['action'],
            notes: $validated['notes'] ?? null,
        );

        return back()->with('success', 'Seguimiento actualizado.');
    }

    /**
     * @param  array<int,int>  $allowedSupplierIds
     */
    private function stats(int $branchId, array $allowedSupplierIds): array
    {
        $startOfDay = Carbon::today()->startOfDay();
        $endOfDay = Carbon::today()->endOfDay();
        $awaiting = ['pending', 'awaiting_physical', 'detected', 'receiving'];

        $averageWaitingMinutes = (float) (PurchaseInvoice::query()
            ->where('branch_id', $branchId)
            ->whereIn('supplier_id', $allowedSupplierIds)
            ->whereIn('status', $awaiting)
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - created_at)) / 60) as aggregate')
            ->value('aggregate') ?? 0);

        return [
            'detectedToday' => PurchaseInvoice::query()
                ->where('branch_id', $branchId)
                ->whereIn('supplier_id', $allowedSupplierIds)
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->count(),
            'awaitingPhysical' => PurchaseInvoice::query()
                ->where('branch_id', $branchId)
                ->whereIn('supplier_id', $allowedSupplierIds)
                ->whereIn('status', $awaiting)
                ->count(),
            'withDiscrepancy' => PurchaseInvoice::query()
                ->where('branch_id', $branchId)
                ->whereIn('supplier_id', $allowedSupplierIds)
                ->where('status', 'received_discrepancy')
                ->count(),
            'receivedToday' => PurchaseInvoice::query()
                ->where('branch_id', $branchId)
                ->whereIn('supplier_id', $allowedSupplierIds)
                ->whereIn('status', ['received_ok', 'received_discrepancy', 'closed'])
                ->whereBetween('updated_at', [$startOfDay, $endOfDay])
                ->count(),
            'averageWaitingHours' => $averageWaitingMinutes > 0
                ? round($averageWaitingMinutes / 60, 1)
                : 0,
        ];
    }

    private function invoiceListData(PurchaseInvoice $invoice): array
    {
        $confirmation = $invoice->receptionConfirmation;
        $items = $confirmation?->items ?? collect();

        return [
            'id' => $invoice->id,
            'supplier' => $invoice->supplier?->name ?? 'Proveedor sin nombre',
            'invoiceNumber' => $invoice->invoice_number,
            'total' => (float) $invoice->total,
            'status' => $invoice->status,
            'statusLabel' => $this->statusLabel($invoice->status),
            'emissionDate' => $invoice->emission_date?->format('d/m/Y'),
            'detectedAt' => $invoice->created_at?->format('d/m/Y H:i'),
            'ageLabel' => $invoice->created_at?->diffForHumans(short: true),
            'itemsCount' => $invoice->items_count ?? $invoice->items()->count(),
            'discrepanciesCount' => $items->where('has_discrepancy', true)->count(),
        ];
    }

    private function invoiceDetailData(PurchaseInvoice $invoice): array
    {
        $confirmation = $invoice->receptionConfirmation;

        return [
            ...$this->invoiceListData($invoice),
            'accessKey' => $invoice->access_key,
            'fromEmail' => $invoice->from_email,
            'confirmation' => $confirmation ? [
                'id' => $confirmation->id,
                'status' => $confirmation->status,
                'notes' => $confirmation->notes,
                'confirmedAt' => $confirmation->confirmed_at?->format('d/m/Y H:i'),
                'items' => $confirmation->items->map(fn ($item) => [
                    'id' => $item->id,
                    'purchaseInvoiceItemId' => $item->purchase_invoice_item_id,
                    'code' => $item->tini_product_id,
                    'description' => $item->description,
                    'expectedQty' => (float) $item->expected_qty,
                    'receivedQty' => (float) $item->received_qty,
                    'conditionStatus' => $item->condition_status,
                    'hasDiscrepancy' => $item->has_discrepancy,
                    'discrepancyNotes' => $item->discrepancy_notes,
                ])->values(),
            ] : null,
            'invoiceItems' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unitPrice' => (float) $item->unit_price,
                'subtotal' => (float) $item->subtotal,
            ])->values(),
            'events' => $invoice->events
                ->sortBy('created_at')
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'body' => $event->body,
                    'user' => $event->user?->name,
                    'createdAt' => $event->created_at?->format('d/m/Y H:i'),
                ])
                ->values(),
        ];
    }

    private function statusOptions(): array
    {
        return [
            ['value' => 'all', 'label' => 'Todos los estados'],
            ['value' => 'awaiting_physical', 'label' => 'Esperando bodega'],
            ['value' => 'receiving', 'label' => 'En recepcion'],
            ['value' => 'received_ok', 'label' => 'Conforme'],
            ['value' => 'received_discrepancy', 'label' => 'Con novedad'],
            ['value' => 'closed', 'label' => 'Cerrado'],
        ];
    }

    private function transferTasks(int $branchId): array
    {
        $base = BranchTransfer::query()
            ->with(['sourceBranch', 'destinationBranch', 'requestedBy', 'items'])
            ->latest('created_at');

        $outbound = (clone $base)
            ->where('source_branch_id', $branchId)
            ->whereIn('status', [
                BranchTransfer::STATUS_REQUESTED,
                BranchTransfer::STATUS_PREPARING,
                BranchTransfer::STATUS_READY_TO_SHIP,
            ])
            ->limit(30)
            ->get()
            ->map(fn (BranchTransfer $transfer) => $this->transferTaskData($transfer, 'outbound'))
            ->values();

        $inbound = (clone $base)
            ->where('destination_branch_id', $branchId)
            ->where('status', BranchTransfer::STATUS_IN_TRANSIT)
            ->limit(30)
            ->get()
            ->map(fn (BranchTransfer $transfer) => $this->transferTaskData($transfer, 'inbound'))
            ->values();

        return [
            'outbound' => $outbound,
            'inbound' => $inbound,
            'counts' => [
                'outbound' => $outbound->count(),
                'inbound' => $inbound->count(),
            ],
        ];
    }

    private function transferTaskData(BranchTransfer $transfer, string $direction): array
    {
        return [
            'id' => $transfer->id,
            'direction' => $direction,
            'status' => $transfer->status,
            'statusLabel' => $this->transferStatusLabel($transfer->status),
            'sourceBranch' => [
                'id' => $transfer->sourceBranch->id,
                'displayName' => $transfer->sourceBranch->display_name,
                'warehouseCode' => $transfer->sourceBranch->warehouse_code,
            ],
            'destinationBranch' => [
                'id' => $transfer->destinationBranch->id,
                'displayName' => $transfer->destinationBranch->display_name,
                'warehouseCode' => $transfer->destinationBranch->warehouse_code,
            ],
            'requestedBy' => $transfer->requestedBy?->name,
            'createdAt' => $transfer->created_at?->format('d/m/Y H:i'),
            'items' => $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'productCode' => $item->product_code,
                'productName' => $item->product_name,
                'unit' => $item->unit,
                'requestedQty' => (float) $item->requested_qty,
                'preparedQty' => $item->prepared_qty !== null ? (float) $item->prepared_qty : null,
                'receivedQty' => $item->received_qty !== null ? (float) $item->received_qty : null,
                'sourceStockSnapshot' => $item->source_stock_snapshot !== null ? (float) $item->source_stock_snapshot : null,
                'sourceStockVerified' => $item->source_stock_verified,
            ])->values(),
        ];
    }

    private function transferStatusLabel(string $status): string
    {
        return match ($status) {
            BranchTransfer::STATUS_REQUESTED => 'Por preparar',
            BranchTransfer::STATUS_PREPARING => 'Preparando',
            BranchTransfer::STATUS_READY_TO_SHIP => 'Listo para enviar',
            BranchTransfer::STATUS_IN_TRANSIT => 'En camino',
            default => $status,
        };
    }

    private function statusLabel(string $status): string
    {
        return collect($this->statusOptions())->firstWhere('value', $status)['label'] ?? 'Detectada';
    }

    private function receiptViewMode(Request $request): string
    {
        $user = $request->user();

        if ($user->hasAnyRole(config('internal.warehouse_roles', []))
            && ! $user->hasAnyRole(config('internal.receipt_review_roles', []))
            && ! $user->hasGlobalBranchAccess()) {
            return 'warehouse';
        }

        return 'admin';
    }

    private function canReceive(Request $request): bool
    {
        return $request->user()->hasAnyRole(config('internal.warehouse_roles', []));
    }

    private function canViewReceipts(Request $request): bool
    {
        return $request->user()->hasAnyRole(config('internal.receipt_view_roles', []))
            || $request->user()->hasGlobalBranchAccess();
    }

    private function canManageReview(Request $request): bool
    {
        return $request->user()->hasAnyRole(config('internal.receipt_review_roles', []))
            || $request->user()->hasGlobalBranchAccess();
    }

    private function findConfirmationForUser(int $confirmationId, Request $request): ReceptionConfirmation
    {
        $confirmation = ReceptionConfirmation::withoutBranchScope()
            ->with('invoice')
            ->findOrFail($confirmationId);

        abort_unless($request->user()->canAccessBranch((int) $confirmation->branch_id), 403);

        return $confirmation;
    }

    private function findInvoiceForUser(int $invoiceId, Request $request): PurchaseInvoice
    {
        $invoice = PurchaseInvoice::withoutBranchScope()->findOrFail($invoiceId);

        abort_unless($request->user()->canAccessBranch((int) $invoice->branch_id), 403);

        return $invoice;
    }
}
