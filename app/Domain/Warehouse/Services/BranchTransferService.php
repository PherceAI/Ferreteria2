<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Notifications\DTOs\WebPushData;
use App\Domain\Notifications\Services\PushNotificationService;
use App\Domain\Warehouse\Models\BranchTransfer;
use App\Domain\Warehouse\Models\BranchTransferEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BranchTransferService
{
    public function __construct(
        private readonly PushNotificationService $pushNotifications,
    ) {}

    /**
     * @param  array<int, array{inventory_product_id?:int|null, product_code?:string|null, product_name?:string|null, unit?:string|null, requested_qty:numeric-string|float|int}>  $items
     */
    public function createRequest(
        int $sourceBranchId,
        int $destinationBranchId,
        array $items,
        User $user,
        ?string $notes = null,
        ?string $requestKey = null,
    ): BranchTransfer {
        if ($sourceBranchId === $destinationBranchId) {
            throw ValidationException::withMessages([
                'source_branch_id' => 'La sucursal fuente debe ser diferente de la sucursal solicitante.',
            ]);
        }

        if (! $user->hasGlobalBranchAccess() && ! $user->canAccessBranch($destinationBranchId)) {
            throw ValidationException::withMessages([
                'destination_branch_id' => 'No puedes solicitar traspasos para una sucursal no asignada.',
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Debes agregar al menos un producto.',
            ]);
        }

        $this->ensureCanCreate($user);
        $requestKey = $this->normalizeRequestKey($requestKey);

        if ($requestKey !== null) {
            $existing = $this->findByRequestKey($requestKey);

            if ($existing instanceof BranchTransfer) {
                return $existing;
            }
        }

        try {
            return DB::transaction(function () use ($sourceBranchId, $destinationBranchId, $items, $user, $notes, $requestKey): BranchTransfer {
                $transfer = BranchTransfer::create([
                    'source_branch_id' => $sourceBranchId,
                    'destination_branch_id' => $destinationBranchId,
                    'requested_by' => $user->id,
                    'request_key' => $requestKey,
                    'status' => BranchTransfer::STATUS_REQUESTED,
                    'request_notes' => $notes,
                ]);

                foreach ($items as $index => $item) {
                    $this->createTransferItem($transfer, $item, $sourceBranchId, $index);
                }

                $this->recordEvent(
                    transfer: $transfer,
                    user: $user,
                    type: 'requested',
                    title: 'Solicitud de traspaso creada',
                    body: 'La sucursal solicitante pidio productos a bodega fuente.',
                );

                $this->pushNotifications->sendToRolesInBranch(
                    branchId: $sourceBranchId,
                    roles: config('internal.warehouse_roles', ['Bodeguero']),
                    data: new WebPushData(
                        title: 'Nueva solicitud de traspaso',
                        body: 'Hay productos pendientes de preparar para otra sucursal.',
                        url: '/inventory/transfers?tab=preparation',
                        tag: "branch-transfer-{$transfer->id}",
                        severity: 'warning',
                    ),
                );

                return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
            });
        } catch (QueryException $exception) {
            if ($requestKey === null || ! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $existing = $this->findByRequestKey($requestKey);

            if ($existing instanceof BranchTransfer) {
                return $existing;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, array{id:int, prepared_qty:numeric-string|float|int, preparation_notes?:string|null}>  $items
     */
    public function startPreparing(BranchTransfer $transfer, User $user, array $items, ?string $notes = null): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $items, $notes): BranchTransfer {
            $transfer = $this->lockTransfer($transfer);
            $this->ensureStatus($transfer, [BranchTransfer::STATUS_REQUESTED]);
            $this->ensureCanPrepare($transfer, $user);
            $this->updatePreparedItems($transfer, $items);

            $transfer->update([
                'status' => BranchTransfer::STATUS_PREPARING,
                'prepared_by' => $user->id,
                'preparation_notes' => $notes,
                'preparing_at' => Carbon::now(),
            ]);

            $this->recordEvent(
                transfer: $transfer,
                user: $user,
                type: 'preparing',
                title: 'Bodega inicio preparacion',
                body: 'Los productos solicitados se estan separando en bodega fuente.',
            );

            $this->pushNotifications->sendToBranch(
                branchId: (int) $transfer->source_branch_id,
                data: new WebPushData(
                    title: 'Producto comprometido en traspaso',
                    body: 'Bodega empezo a preparar productos para otra sucursal.',
                    url: '/inventory/transfers?tab=preparation',
                    tag: "branch-transfer-{$transfer->id}-preparing",
                    severity: 'info',
                ),
            );

            return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
        });
    }

    public function markReadyToShip(BranchTransfer $transfer, User $user, ?string $notes = null): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $notes): BranchTransfer {
            $transfer = $this->lockTransfer($transfer);
            $this->ensureStatus($transfer, [BranchTransfer::STATUS_PREPARING]);
            $this->ensureCanPrepare($transfer, $user);

            $transfer->update([
                'status' => BranchTransfer::STATUS_READY_TO_SHIP,
                'preparation_notes' => $notes ?? $transfer->preparation_notes,
                'ready_to_ship_at' => Carbon::now(),
            ]);

            $this->recordEvent(
                transfer: $transfer,
                user: $user,
                type: 'ready_to_ship',
                title: 'Orden montada al camion',
                body: 'Bodega termino de preparar el traspaso.',
            );

            return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
        });
    }

    public function ship(BranchTransfer $transfer, User $user, ?string $notes = null): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $notes): BranchTransfer {
            $transfer = $this->lockTransfer($transfer);
            $this->ensureStatus($transfer, [BranchTransfer::STATUS_READY_TO_SHIP]);
            $this->ensureCanPrepare($transfer, $user);

            $transfer->update([
                'status' => BranchTransfer::STATUS_IN_TRANSIT,
                'shipped_by' => $user->id,
                'shipping_notes' => $notes,
                'shipped_at' => Carbon::now(),
            ]);

            $this->recordEvent(
                transfer: $transfer,
                user: $user,
                type: 'in_transit',
                title: 'Traspaso enviado',
                body: 'La mercaderia salio de bodega fuente y queda pendiente de recepcion.',
            );

            $this->pushNotifications->sendToRolesInBranch(
                branchId: (int) $transfer->destination_branch_id,
                roles: config('internal.warehouse_roles', ['Bodeguero']),
                data: new WebPushData(
                    title: 'Traspaso en camino',
                    body: 'Hay una tarea de recepcion pendiente para tu sucursal.',
                    url: '/inventory/transfers?tab=reception',
                    tag: "branch-transfer-{$transfer->id}-in-transit",
                    severity: 'warning',
                ),
            );

            return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
        });
    }

    /**
     * @param  array<int, array{id:int, received_qty:numeric-string|float|int, reception_notes?:string|null}>  $items
     */
    public function receive(BranchTransfer $transfer, User $user, array $items, ?string $notes = null): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $items, $notes): BranchTransfer {
            $transfer = $this->lockTransfer($transfer);
            $this->ensureStatus($transfer, [BranchTransfer::STATUS_IN_TRANSIT]);
            $this->ensureCanReceive($transfer, $user);

            $hasDiscrepancy = $this->updateReceivedItems($transfer, $items);

            $transfer->update([
                'status' => $hasDiscrepancy
                    ? BranchTransfer::STATUS_RECEIVED_DISCREPANCY
                    : BranchTransfer::STATUS_RECEIVED,
                'received_by' => $user->id,
                'reception_notes' => $notes,
                'received_at' => Carbon::now(),
            ]);

            $this->recordEvent(
                transfer: $transfer,
                user: $user,
                type: $hasDiscrepancy ? 'received_discrepancy' : 'received',
                title: $hasDiscrepancy ? 'Recepcion con diferencias' : 'Recepcion confirmada',
                body: $hasDiscrepancy
                    ? 'Bodega receptora reporto diferencias entre enviado y recibido.'
                    : 'Bodega receptora confirmo que todo llego conforme.',
            );

            $this->pushNotifications->sendToRolesInBranch(
                branchId: (int) $transfer->destination_branch_id,
                roles: config('internal.inventory_control_roles', ['Encargado Inventario']),
                data: new WebPushData(
                    title: 'Producto recibido en local',
                    body: 'El traspaso llego y esta pendiente de formalizacion en TINI.',
                    url: '/inventory/transfers?tab=reception',
                    tag: "branch-transfer-{$transfer->id}-received",
                    severity: $hasDiscrepancy ? 'warning' : 'info',
                ),
            );

            return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
        });
    }

    public function completeTini(BranchTransfer $transfer, User $user): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $user): BranchTransfer {
            $transfer = $this->lockTransfer($transfer);
            $this->ensureStatus($transfer, [
                BranchTransfer::STATUS_RECEIVED,
                BranchTransfer::STATUS_RECEIVED_DISCREPANCY,
            ]);
            $this->ensureCanCompleteTini($transfer, $user);

            $transfer->update([
                'status' => BranchTransfer::STATUS_TINI_COMPLETED,
                'tini_completed_by' => $user->id,
                'tini_completed_at' => Carbon::now(),
            ]);

            $this->recordEvent(
                transfer: $transfer,
                user: $user,
                type: 'tini_completed',
                title: 'Traspaso formalizado en TINI',
                body: 'El usuario confirmo que el movimiento ya fue registrado en TINI.',
            );

            return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
        });
    }

    public function cancel(BranchTransfer $transfer, User $user, ?string $reason = null): BranchTransfer
    {
        if (! $this->canCancel($transfer, $user)) {
            throw ValidationException::withMessages([
                'transfer' => 'No tienes permiso para cancelar este traspaso.',
            ]);
        }

        return DB::transaction(function () use ($transfer, $user, $reason): BranchTransfer {
            $transfer = $this->lockTransfer($transfer);

            if (! $this->canCancel($transfer, $user)) {
                throw ValidationException::withMessages([
                    'transfer' => 'No tienes permiso para cancelar este traspaso.',
                ]);
            }

            $transfer->update([
                'status' => BranchTransfer::STATUS_CANCELLED,
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
                'cancelled_at' => Carbon::now(),
            ]);

            $this->recordEvent(
                transfer: $transfer,
                user: $user,
                type: 'cancelled',
                title: 'Traspaso cancelado',
                body: $reason,
            );

            return $transfer->refresh()->load(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user']);
        });
    }

    public function canCreate(User $user): bool
    {
        return $user->hasGlobalBranchAccess()
            || $user->hasAnyRole(config('internal.transfer_create_roles', []));
    }

    public function canPrepare(BranchTransfer $transfer, User $user): bool
    {
        if (! in_array($transfer->status, [
            BranchTransfer::STATUS_REQUESTED,
            BranchTransfer::STATUS_PREPARING,
            BranchTransfer::STATUS_READY_TO_SHIP,
        ], true)) {
            return false;
        }

        return $user->hasGlobalBranchAccess()
            || ($user->hasAnyRole(config('internal.inventory_control_roles', [])) && $user->canAccessBranch((int) $transfer->source_branch_id))
            || ($user->hasAnyRole(config('internal.warehouse_roles', [])) && $user->canAccessBranch((int) $transfer->source_branch_id));
    }

    public function canReceive(BranchTransfer $transfer, User $user): bool
    {
        if ($transfer->status !== BranchTransfer::STATUS_IN_TRANSIT) {
            return false;
        }

        return $user->hasGlobalBranchAccess()
            || ($user->hasAnyRole(config('internal.inventory_control_roles', [])) && $user->canAccessBranch((int) $transfer->destination_branch_id))
            || ($user->hasAnyRole(config('internal.warehouse_roles', [])) && $user->canAccessBranch((int) $transfer->destination_branch_id));
    }

    public function canCompleteTini(BranchTransfer $transfer, User $user): bool
    {
        if (! in_array($transfer->status, [
            BranchTransfer::STATUS_RECEIVED,
            BranchTransfer::STATUS_RECEIVED_DISCREPANCY,
        ], true)) {
            return false;
        }

        return $user->hasGlobalBranchAccess()
            || (
                $user->hasAnyRole(config('internal.transfer_manage_roles', []))
                && $this->canAccessEitherBranch($transfer, $user)
            );
    }

    public function canCancel(BranchTransfer $transfer, User $user): bool
    {
        if (in_array($transfer->status, [BranchTransfer::STATUS_TINI_COMPLETED, BranchTransfer::STATUS_CANCELLED], true)) {
            return false;
        }

        return $user->hasGlobalBranchAccess()
            || (
                $user->hasAnyRole(config('internal.transfer_manage_roles', []))
                && $this->canAccessEitherBranch($transfer, $user)
            );
    }

    public function canView(BranchTransfer $transfer, User $user): bool
    {
        return $user->hasGlobalBranchAccess()
            || $this->canAccessEitherBranch($transfer, $user);
    }

    private function lockTransfer(BranchTransfer $transfer): BranchTransfer
    {
        return BranchTransfer::query()
            ->whereKey($transfer->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function findByRequestKey(string $requestKey): ?BranchTransfer
    {
        return BranchTransfer::query()
            ->where('request_key', $requestKey)
            ->with(['sourceBranch', 'destinationBranch', 'requestedBy', 'items', 'events.user'])
            ->first();
    }

    private function normalizeRequestKey(?string $requestKey): ?string
    {
        $requestKey = trim((string) $requestKey);

        return $requestKey === '' ? null : hash('sha256', $requestKey);
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23505';
    }

    private function createTransferItem(BranchTransfer $transfer, array $item, int $sourceBranchId, int $index): void
    {
        $requestedQty = (float) $item['requested_qty'];

        if ($requestedQty <= 0) {
            throw ValidationException::withMessages([
                "items.{$index}.requested_qty" => 'La cantidad solicitada debe ser mayor a cero.',
            ]);
        }

        $product = null;
        $productId = isset($item['inventory_product_id']) ? (int) $item['inventory_product_id'] : null;

        if ($productId !== null && $productId > 0) {
            $product = InventoryProduct::withoutBranchScope()
                ->where('branch_id', $sourceBranchId)
                ->find($productId);

            if (! $product instanceof InventoryProduct) {
                throw ValidationException::withMessages([
                    "items.{$index}.inventory_product_id" => 'El producto no pertenece a la sucursal fuente.',
                ]);
            }
        }

        $productCode = trim((string) ($product?->code ?? $item['product_code'] ?? ''));
        $productName = trim((string) ($product?->name ?? $item['product_name'] ?? ''));

        if ($productCode === '' || $productName === '') {
            throw ValidationException::withMessages([
                "items.{$index}.product_name" => 'Debes indicar codigo y nombre del producto.',
            ]);
        }

        $transfer->items()->create([
            'inventory_product_id' => $product?->id,
            'product_code' => $productCode,
            'product_name' => $productName,
            'unit' => $product?->unit ?? ($item['unit'] ?? null),
            'source_stock_snapshot' => $product?->current_stock,
            'source_stock_verified' => $product instanceof InventoryProduct,
            'requested_qty' => $requestedQty,
        ]);
    }

    private function updatePreparedItems(BranchTransfer $transfer, array $items): void
    {
        $transfer->loadMissing('items');
        $payloadById = collect($items)->keyBy('id');
        $this->ensureSubmittedItemsMatch($transfer, array_keys($payloadById->all()));

        foreach ($transfer->items as $item) {
            $payload = $payloadById->get($item->id);
            $preparedQty = (float) $payload['prepared_qty'];

            if ($preparedQty < 0) {
                throw ValidationException::withMessages([
                    'items' => 'La cantidad preparada no puede ser negativa.',
                ]);
            }

            $item->update([
                'prepared_qty' => $preparedQty,
                'preparation_notes' => $payload['preparation_notes'] ?? null,
            ]);
        }
    }

    private function updateReceivedItems(BranchTransfer $transfer, array $items): bool
    {
        $transfer->loadMissing('items');
        $payloadById = collect($items)->keyBy('id');
        $this->ensureSubmittedItemsMatch($transfer, array_keys($payloadById->all()));
        $hasDiscrepancy = false;

        foreach ($transfer->items as $item) {
            $payload = $payloadById->get($item->id);
            $receivedQty = (float) $payload['received_qty'];

            if ($receivedQty < 0) {
                throw ValidationException::withMessages([
                    'items' => 'La cantidad recibida no puede ser negativa.',
                ]);
            }

            $lineHasDiscrepancy = abs(((float) $item->prepared_qty) - $receivedQty) > 0.0001;
            $hasDiscrepancy = $hasDiscrepancy || $lineHasDiscrepancy;

            $item->update([
                'received_qty' => $receivedQty,
                'has_discrepancy' => $lineHasDiscrepancy,
                'reception_notes' => $payload['reception_notes'] ?? null,
            ]);
        }

        return $hasDiscrepancy;
    }

    /**
     * @param  array<int, int|string>  $submittedIds
     */
    private function ensureSubmittedItemsMatch(BranchTransfer $transfer, array $submittedIds): void
    {
        $submitted = collect($submittedIds)->map(fn ($id) => (int) $id)->sort()->values();
        $existing = $transfer->items->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();

        if ($submitted->count() !== $submitted->unique()->count() || $submitted->all() !== $existing->all()) {
            throw ValidationException::withMessages([
                'items' => 'Debes confirmar todos los items del traspaso sin duplicados ni items ajenos.',
            ]);
        }
    }

    private function recordEvent(
        BranchTransfer $transfer,
        User $user,
        string $type,
        string $title,
        ?string $body = null,
        array $metadata = [],
    ): BranchTransferEvent {
        return $transfer->events()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     */
    private function ensureStatus(BranchTransfer $transfer, array $allowedStatuses): void
    {
        if (! in_array($transfer->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'transfer' => 'El traspaso no esta en un estado valido para esta accion.',
            ]);
        }
    }

    private function ensureCanCreate(User $user): void
    {
        if ($this->canCreate($user)) {
            return;
        }

        throw ValidationException::withMessages([
            'transfer' => 'No tienes permiso para crear solicitudes de traspaso.',
        ]);
    }

    private function ensureCanPrepare(BranchTransfer $transfer, User $user): void
    {
        if ($this->canPrepare($transfer, $user)) {
            return;
        }

        throw ValidationException::withMessages([
            'transfer' => 'No tienes permiso para preparar o enviar este traspaso.',
        ]);
    }

    private function ensureCanReceive(BranchTransfer $transfer, User $user): void
    {
        if ($this->canReceive($transfer, $user)) {
            return;
        }

        throw ValidationException::withMessages([
            'transfer' => 'No tienes permiso para recibir este traspaso.',
        ]);
    }

    private function ensureCanCompleteTini(BranchTransfer $transfer, User $user): void
    {
        if ($this->canCompleteTini($transfer, $user)) {
            return;
        }

        throw ValidationException::withMessages([
            'transfer' => 'No tienes permiso para cerrar la formalizacion en TINI.',
        ]);
    }

    private function canAccessEitherBranch(BranchTransfer $transfer, User $user): bool
    {
        return $user->canAccessBranch((int) $transfer->source_branch_id)
            || $user->canAccessBranch((int) $transfer->destination_branch_id);
    }
}
