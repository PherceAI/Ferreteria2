<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Warehouse\Models\BranchTransfer;
use App\Domain\Warehouse\Services\BranchTransferService;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class BranchTransferController extends Controller
{
    public function __construct(
        private readonly BranchTransferService $transfers,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['requests', 'preparation', 'transit', 'reception', 'closed'])],
            'search' => ['nullable', 'string', 'max:120'],
            'product_search' => ['nullable', 'string', 'max:120'],
        ]);
        $user = $this->user($request);
        $tab = (string) ($validated['tab'] ?? 'requests');
        $search = trim((string) ($validated['search'] ?? ''));
        $productSearch = trim((string) ($validated['product_search'] ?? ''));
        $activeBranchId = Context::get('branch_id');

        $query = BranchTransfer::query()
            ->visibleTo($user)
            ->with(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user'])
            ->latest('created_at');

        $this->applyTabFilter($query, $tab);

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->whereHas('sourceBranch', fn ($branch) => $branch->searchAlias($search))
                    ->orWhereHas('destinationBranch', fn ($branch) => $branch->searchAlias($search))
                    ->orWhereHas('items', function ($item) use ($search): void {
                        $item
                            ->where('product_code', 'like', "%{$search}%")
                            ->orWhere('product_name', 'like', "%{$search}%");
                    });
            });
        }

        $transfers = $query
            ->paginate(15)
            ->withQueryString()
            ->through(fn (BranchTransfer $transfer) => $this->transferData($transfer, $user));

        return Inertia::render('inventory/transfers/index', [
            'branches' => $this->transfers->canCreate($user) ? $this->branchOptions() : [],
            'filters' => [
                'tab' => $tab,
                'search' => $search,
                'source_branch_id' => null,
                'product_search' => $productSearch,
            ],
            'permissions' => [
                'canCreate' => $this->transfers->canCreate($user),
                'activeBranchId' => $activeBranchId,
            ],
            'productOptions' => $this->productOptions($activeBranchId, $productSearch, $user),
            'stats' => $this->stats($user),
            'statusOptions' => $this->statusOptions(),
            'transfers' => $transfers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($this->transfers->canCreate($user), 403);

        $destinationBranchId = Context::get('branch_id');
        abort_if($destinationBranchId === null, 422, 'Debes seleccionar una sucursal activa.');

        $validated = $request->validate([
            'source_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_product_id' => ['nullable', 'integer'],
            'items.*.source_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'items.*.product_code' => ['nullable', 'string', 'max:80'],
            'items.*.product_name' => ['nullable', 'string', 'max:500'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.requested_qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $sourceBranchFallback = $validated['source_branch_id'] ?? null;
        $itemsBySourceBranch = [];
        $baseRequestKey = trim((string) ($validated['idempotency_key'] ?? $request->header('Idempotency-Key', '')));

        foreach ($validated['items'] as $index => $item) {
            $sourceBranchId = $item['source_branch_id'] ?? $sourceBranchFallback;

            if ($sourceBranchId === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.source_branch_id" => 'Selecciona la bodega fuente para este producto.',
                ]);
            }

            $itemsBySourceBranch[(int) $sourceBranchId][] = $item;
        }

        foreach ($itemsBySourceBranch as $sourceBranchId => $items) {
            $this->transfers->createRequest(
                sourceBranchId: $sourceBranchId,
                destinationBranchId: (int) $destinationBranchId,
                items: $items,
                user: $user,
                notes: $validated['notes'] ?? null,
                requestKey: $baseRequestKey === '' ? null : "{$baseRequestKey}:{$sourceBranchId}",
            );
        }

        return redirect()
            ->route('inventory.transfers.index')
            ->with('success', count($itemsBySourceBranch) === 1
                ? 'Solicitud de traspaso creada.'
                : 'Solicitudes de traspaso creadas por bodega fuente.');
    }

    public function startPreparing(BranchTransfer $transfer, Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeTransferVisible($transfer, $user);
        abort_unless($this->transfers->canPrepare($transfer, $user), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.prepared_qty' => ['required', 'numeric', 'min:0'],
            'items.*.preparation_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transfers->startPreparing(
            transfer: $transfer,
            user: $user,
            items: $validated['items'],
            notes: $validated['notes'] ?? null,
        );

        return back()->with('success', 'Preparacion iniciada.');
    }

    public function readyToShip(BranchTransfer $transfer, Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeTransferVisible($transfer, $user);
        abort_unless($this->transfers->canPrepare($transfer, $user), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transfers->markReadyToShip($transfer, $user, $validated['notes'] ?? null);

        return back()->with('success', 'Orden lista para envio.');
    }

    public function ship(BranchTransfer $transfer, Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeTransferVisible($transfer, $user);
        abort_unless($this->transfers->canPrepare($transfer, $user), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transfers->ship($transfer, $user, $validated['notes'] ?? null);

        return back()->with('success', 'Traspaso enviado.');
    }

    public function receive(BranchTransfer $transfer, Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeTransferVisible($transfer, $user);
        abort_unless($this->transfers->canReceive($transfer, $user), 403);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.reception_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transfers->receive(
            transfer: $transfer,
            user: $user,
            items: $validated['items'],
            notes: $validated['notes'] ?? null,
        );

        return back()->with('success', 'Recepcion registrada.');
    }

    public function completeTini(BranchTransfer $transfer, Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeTransferVisible($transfer, $user);
        abort_unless($this->transfers->canCompleteTini($transfer, $user), 403);

        $this->transfers->completeTini($transfer, $user);

        return back()->with('success', 'Traspaso formalizado en TINI.');
    }

    public function cancel(BranchTransfer $transfer, Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->authorizeTransferVisible($transfer, $user);
        abort_unless($this->transfers->canCancel($transfer, $user), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->transfers->cancel($transfer, $user, $validated['reason'] ?? null);

        return back()->with('success', 'Traspaso cancelado.');
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function applyTabFilter($query, string $tab): void
    {
        match ($tab) {
            'preparation' => $query->whereIn('status', [
                BranchTransfer::STATUS_REQUESTED,
                BranchTransfer::STATUS_PREPARING,
                BranchTransfer::STATUS_READY_TO_SHIP,
            ]),
            'transit' => $query->where('status', BranchTransfer::STATUS_IN_TRANSIT),
            'reception' => $query->whereIn('status', [
                BranchTransfer::STATUS_IN_TRANSIT,
                BranchTransfer::STATUS_RECEIVED,
                BranchTransfer::STATUS_RECEIVED_DISCREPANCY,
            ]),
            'closed' => $query->whereIn('status', [
                BranchTransfer::STATUS_TINI_COMPLETED,
                BranchTransfer::STATUS_CANCELLED,
            ]),
            default => $query->whereIn('status', [
                BranchTransfer::STATUS_REQUESTED,
                BranchTransfer::STATUS_PREPARING,
                BranchTransfer::STATUS_READY_TO_SHIP,
            ]),
        };
    }

    /**
     * @return array<int, array{id:int,name:string,displayName:string,code:string,warehouseName:string|null,warehouseCode:string|null,city:string|null}>
     */
    private function branchOptions(): array
    {
        return Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'warehouse_name', 'warehouse_code', 'city'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'displayName' => $branch->display_name,
                'code' => $branch->code,
                'warehouseName' => $branch->warehouse_name,
                'warehouseCode' => $branch->warehouse_code,
                'city' => $branch->city,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productOptions(?int $destinationBranchId, string $search, User $user): array
    {
        if ($search === '' || ! $this->transfers->canCreate($user)) {
            return [];
        }

        $normalizedSearch = mb_strtolower($search);
        $startsWith = "{$normalizedSearch}%";
        $needle = "%{$normalizedSearch}%";

        $products = InventoryProduct::withoutBranchScope()
            ->with('branch')
            ->whereHas('branch', fn ($branch) => $branch->where('is_active', true))
            ->when(
                $destinationBranchId !== null,
                fn ($query) => $query->where('branch_id', '<>', $destinationBranchId),
            )
            ->where(function ($query) use ($needle): void {
                $query
                    ->whereRaw('LOWER(code) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
            })
            ->orderByRaw(
                'CASE
                    WHEN LOWER(code) = ? THEN 0
                    WHEN LOWER(code) LIKE ? THEN 1
                    WHEN LOWER(name) = ? THEN 2
                    WHEN LOWER(name) LIKE ? THEN 3
                    ELSE 4
                END',
                [$normalizedSearch, $startsWith, $normalizedSearch, $startsWith],
            )
            ->orderByDesc('current_stock')
            ->orderBy('name')
            ->limit(24)
            ->get(['id', 'branch_id', 'code', 'name', 'unit', 'current_stock']);

        $bestStockByCode = $products
            ->groupBy(fn (InventoryProduct $product) => mb_strtolower($product->code))
            ->map(fn ($matches) => (float) $matches->max('current_stock'));

        return $products
            ->map(fn (InventoryProduct $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'unit' => $product->unit,
                'current_stock' => (float) $product->current_stock,
                'isRecommended' => (float) $product->current_stock >= ($bestStockByCode[mb_strtolower($product->code)] ?? 0.0),
                'branch' => [
                    'id' => $product->branch->id,
                    'name' => $product->branch->name,
                    'displayName' => $product->branch->display_name,
                    'code' => $product->branch->code,
                    'warehouseName' => $product->branch->warehouse_name,
                    'warehouseCode' => $product->branch->warehouse_code,
                    'city' => $product->branch->city,
                ],
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function stats(User $user): array
    {
        $query = BranchTransfer::query()->visibleTo($user);

        return [
            'open' => (clone $query)->whereNotIn('status', [
                BranchTransfer::STATUS_TINI_COMPLETED,
                BranchTransfer::STATUS_CANCELLED,
            ])->count(),
            'preparing' => (clone $query)->whereIn('status', [
                BranchTransfer::STATUS_REQUESTED,
                BranchTransfer::STATUS_PREPARING,
                BranchTransfer::STATUS_READY_TO_SHIP,
            ])->count(),
            'inTransit' => (clone $query)->where('status', BranchTransfer::STATUS_IN_TRANSIT)->count(),
            'withDiscrepancy' => (clone $query)->where('status', BranchTransfer::STATUS_RECEIVED_DISCREPANCY)->count(),
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    private function statusOptions(): array
    {
        return BranchTransfer::statusOptions();
    }

    private function transferData(BranchTransfer $transfer, User $user): array
    {
        return [
            'id' => $transfer->id,
            'status' => $transfer->status,
            'statusLabel' => BranchTransfer::statusLabel($transfer->status),
            'sourceBranch' => [
                'id' => $transfer->sourceBranch->id,
                'name' => $transfer->sourceBranch->name,
                'displayName' => $transfer->sourceBranch->display_name,
                'code' => $transfer->sourceBranch->code,
                'warehouseName' => $transfer->sourceBranch->warehouse_name,
                'warehouseCode' => $transfer->sourceBranch->warehouse_code,
            ],
            'destinationBranch' => [
                'id' => $transfer->destinationBranch->id,
                'name' => $transfer->destinationBranch->name,
                'displayName' => $transfer->destinationBranch->display_name,
                'code' => $transfer->destinationBranch->code,
                'warehouseName' => $transfer->destinationBranch->warehouse_name,
                'warehouseCode' => $transfer->destinationBranch->warehouse_code,
            ],
            'requestedBy' => $transfer->requestedBy?->name,
            'requestNotes' => $transfer->request_notes,
            'createdAt' => $transfer->created_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
            'updatedAt' => $transfer->updated_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
            'items' => $transfer->items->map(fn ($item) => [
                'id' => $item->id,
                'productCode' => $item->product_code,
                'productName' => $item->product_name,
                'unit' => $item->unit,
                'sourceStockSnapshot' => $item->source_stock_snapshot !== null ? (float) $item->source_stock_snapshot : null,
                'sourceStockVerified' => $item->source_stock_verified,
                'requestedQty' => (float) $item->requested_qty,
                'preparedQty' => $item->prepared_qty !== null ? (float) $item->prepared_qty : null,
                'receivedQty' => $item->received_qty !== null ? (float) $item->received_qty : null,
                'hasDiscrepancy' => $item->has_discrepancy,
                'preparationNotes' => $item->preparation_notes,
                'receptionNotes' => $item->reception_notes,
            ])->values(),
            'events' => $transfer->events
                ->sortBy('created_at')
                ->map(fn ($event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'title' => $event->title,
                    'body' => $event->body,
                    'user' => $event->user?->name,
                    'createdAt' => $event->created_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
                ])
                ->values(),
            'permissions' => [
                'canPrepare' => $this->transfers->canPrepare($transfer, $user),
                'canReceive' => $this->transfers->canReceive($transfer, $user),
                'canCompleteTini' => $this->transfers->canCompleteTini($transfer, $user),
                'canCancel' => $this->transfers->canCancel($transfer, $user),
            ],
        ];
    }

    private function authorizeTransferVisible(BranchTransfer $transfer, User $user): void
    {
        abort_unless(
            $this->transfers->canView($transfer, $user),
            403,
        );
    }
}
