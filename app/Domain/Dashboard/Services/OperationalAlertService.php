<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Warehouse\Models\BranchTransfer;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Collection;

final class OperationalAlertService
{
    /**
     * @return array<int, array{id:string,type:string,title:string,message:string,timestamp:string,href:string,actionText:string,isRead:bool}>
     */
    public function forUser(User $user, int $limit = 8): array
    {
        $branchIds = $this->visibleBranchIds($user);

        if ($branchIds === []) {
            return [];
        }

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $alerts = collect()
            ->merge($this->invoiceAlerts($branchIds, $branchNames))
            ->merge($this->transferAlerts($branchIds, $branchNames))
            ->merge($this->inventoryAlerts($branchIds, $branchNames));

        return $alerts
            ->sortByDesc('sort')
            ->take($limit)
            ->map(fn (array $alert): array => [
                'id' => $alert['id'],
                'type' => $alert['type'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'timestamp' => $alert['timestamp'],
                'href' => $alert['href'],
                'actionText' => $alert['actionText'],
                'isRead' => false,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function visibleBranchIds(User $user): array
    {
        if ($user->hasGlobalBranchAccess()) {
            return Branch::query()
                ->where('is_active', true)
                ->orderByDesc('is_headquarters')
                ->orderBy('name')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $branchIds = $user->branches()
            ->where('branches.is_active', true)
            ->pluck('branches.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($branchIds === [] && $user->active_branch_id !== null) {
            return [(int) $user->active_branch_id];
        }

        return $branchIds;
    }

    /**
     * @param  array<int, int>  $branchIds
     * @param  Collection<int, string>  $branchNames
     * @return Collection<int, array<string, mixed>>
     */
    private function invoiceAlerts(array $branchIds, Collection $branchNames): Collection
    {
        return PurchaseInvoice::withoutBranchScope()
            ->with('supplier')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['awaiting_physical', 'receiving', 'received_discrepancy'])
            ->latest('updated_at')
            ->limit(4)
            ->get()
            ->map(function (PurchaseInvoice $invoice) use ($branchNames): array {
                $isDiscrepancy = $invoice->status === 'received_discrepancy';
                $branchName = $branchNames[(int) $invoice->branch_id] ?? 'Sucursal';

                return [
                    'id' => "invoice-{$invoice->id}",
                    'type' => $isDiscrepancy ? 'critical' : 'high',
                    'title' => $isDiscrepancy ? 'Factura con novedad' : 'Recepcion pendiente',
                    'message' => sprintf(
                        '%s: factura %s de %s.',
                        $branchName,
                        $invoice->invoice_number,
                        $invoice->supplier?->name ?? 'proveedor sin nombre',
                    ),
                    'timestamp' => $invoice->updated_at?->diffForHumans(short: true) ?? '',
                    'href' => route('purchasing.receipt.index', ['invoice' => $invoice->id], false),
                    'actionText' => 'Abrir recepcion',
                    'sort' => $invoice->updated_at?->getTimestamp() ?? 0,
                ];
            });
    }

    /**
     * @param  array<int, int>  $branchIds
     * @param  Collection<int, string>  $branchNames
     * @return Collection<int, array<string, mixed>>
     */
    private function transferAlerts(array $branchIds, Collection $branchNames): Collection
    {
        return BranchTransfer::query()
            ->where(function ($query) use ($branchIds): void {
                $query
                    ->whereIn('source_branch_id', $branchIds)
                    ->orWhereIn('destination_branch_id', $branchIds);
            })
            ->whereIn('status', [
                BranchTransfer::STATUS_REQUESTED,
                BranchTransfer::STATUS_PREPARING,
                BranchTransfer::STATUS_READY_TO_SHIP,
                BranchTransfer::STATUS_IN_TRANSIT,
                BranchTransfer::STATUS_RECEIVED_DISCREPANCY,
            ])
            ->latest('updated_at')
            ->limit(3)
            ->get()
            ->map(function (BranchTransfer $transfer) use ($branchNames): array {
                $source = $branchNames[(int) $transfer->source_branch_id] ?? 'Origen';
                $destination = $branchNames[(int) $transfer->destination_branch_id] ?? 'Destino';

                return [
                    'id' => "transfer-{$transfer->id}",
                    'type' => $transfer->status === BranchTransfer::STATUS_RECEIVED_DISCREPANCY ? 'critical' : 'medium',
                    'title' => 'Traspaso activo',
                    'message' => "{$source} hacia {$destination}: {$this->transferStatusLabel($transfer->status)}.",
                    'timestamp' => $transfer->updated_at?->diffForHumans(short: true) ?? '',
                    'href' => route('inventory.transfers.index', false),
                    'actionText' => 'Ver traspasos',
                    'sort' => $transfer->updated_at?->getTimestamp() ?? 0,
                ];
            });
    }

    /**
     * @param  array<int, int>  $branchIds
     * @param  Collection<int, string>  $branchNames
     * @return Collection<int, array<string, mixed>>
     */
    private function inventoryAlerts(array $branchIds, Collection $branchNames): Collection
    {
        return InventoryProduct::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->where('min_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->orderBy('current_stock')
            ->limit(4)
            ->get()
            ->map(function (InventoryProduct $product) use ($branchNames): array {
                $branchName = $branchNames[(int) $product->branch_id] ?? 'Sucursal';

                return [
                    'id' => "low-stock-{$product->id}",
                    'type' => ((float) $product->current_stock) <= 0 ? 'critical' : 'high',
                    'title' => 'Stock bajo',
                    'message' => sprintf(
                        '%s: %s tiene %s %s disponibles.',
                        $branchName,
                        $product->name,
                        number_format((float) $product->current_stock, 2),
                        $product->unit ?? 'unidades',
                    ),
                    'timestamp' => $product->inventory_updated_at?->diffForHumans(short: true)
                        ?? $product->updated_at?->diffForHumans(short: true)
                        ?? '',
                    'href' => route('inventory.products.index', ['search' => $product->code], false),
                    'actionText' => 'Ver producto',
                    'sort' => $product->inventory_updated_at?->getTimestamp()
                        ?? $product->updated_at?->getTimestamp()
                        ?? 0,
                ];
            });
    }

    private function transferStatusLabel(string $status): string
    {
        return match ($status) {
            BranchTransfer::STATUS_REQUESTED => 'por preparar',
            BranchTransfer::STATUS_PREPARING => 'en preparacion',
            BranchTransfer::STATUS_READY_TO_SHIP => 'listo para enviar',
            BranchTransfer::STATUS_IN_TRANSIT => 'en transito',
            BranchTransfer::STATUS_RECEIVED_DISCREPANCY => 'recibido con novedad',
            default => $status,
        };
    }
}
