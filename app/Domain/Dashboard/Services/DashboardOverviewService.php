<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Domain\Warehouse\Models\BranchTransfer;
use App\Models\Branch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DashboardOverviewService
{
    public function __construct(
        private readonly OperationalAlertService $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $branchIds = $this->alerts->visibleBranchIds($user);

        if ($branchIds === []) {
            return $this->emptyOverview();
        }

        $branches = Branch::query()
            ->whereIn('id', $branchIds)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name']);

        $inventoryBase = InventoryProduct::withoutBranchScope()
            ->whereIn('branch_id', $branchIds);

        $invoiceBase = PurchaseInvoice::withoutBranchScope()
            ->whereIn('branch_id', $branchIds);

        $activeTransferStatuses = [
            BranchTransfer::STATUS_REQUESTED,
            BranchTransfer::STATUS_PREPARING,
            BranchTransfer::STATUS_READY_TO_SHIP,
            BranchTransfer::STATUS_IN_TRANSIT,
            BranchTransfer::STATUS_RECEIVED_DISCREPANCY,
        ];

        return [
            'scopeLabel' => $user->hasGlobalBranchAccess()
                ? 'Todas las sucursales'
                : ($user->activeBranch?->display_name ?? 'Sucursal actual'),
            'summary' => [
                'inventoryValue' => (float) (clone $inventoryBase)
                    ->selectRaw('SUM(COALESCE(total_cost, current_stock * COALESCE(cost, 0), 0)) as aggregate')
                    ->value('aggregate'),
                'products' => (clone $inventoryBase)->count(),
                'lowStock' => (clone $inventoryBase)
                    ->where('min_stock', '>', 0)
                    ->whereColumn('current_stock', '<=', 'min_stock')
                    ->count(),
                'zeroStock' => (clone $inventoryBase)->where('current_stock', '<=', 0)->count(),
                'pendingReceipts' => (clone $invoiceBase)
                    ->whereIn('status', ['awaiting_physical', 'receiving'])
                    ->count(),
                'discrepancies' => (clone $invoiceBase)
                    ->where('status', 'received_discrepancy')
                    ->count(),
                'activeTransfers' => BranchTransfer::query()
                    ->where(function ($query) use ($branchIds): void {
                        $query
                            ->whereIn('source_branch_id', $branchIds)
                            ->orWhereIn('destination_branch_id', $branchIds);
                    })
                    ->whereIn('status', $activeTransferStatuses)
                    ->count(),
            ],
            'inventoryByBranch' => $this->inventoryByBranch($branches, $branchIds),
            'invoiceTrend' => $this->invoiceTrend($branchIds),
            'topProducts' => $this->topProducts($branchIds),
            'receptionStatus' => $this->receptionStatus($branchIds),
            'urgentAlerts' => $this->alerts->forUser($user, 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyOverview(): array
    {
        return [
            'scopeLabel' => 'Sin sucursal',
            'summary' => [
                'inventoryValue' => 0,
                'products' => 0,
                'lowStock' => 0,
                'zeroStock' => 0,
                'pendingReceipts' => 0,
                'discrepancies' => 0,
                'activeTransfers' => 0,
            ],
            'inventoryByBranch' => [],
            'invoiceTrend' => [],
            'topProducts' => [],
            'receptionStatus' => [],
            'urgentAlerts' => [],
        ];
    }

    /**
     * @param  iterable<Branch>  $branches
     * @param  array<int, int>  $branchIds
     * @return array<int, array{name:string,total:float}>
     */
    private function inventoryByBranch(iterable $branches, array $branchIds): array
    {
        $values = InventoryProduct::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('branch_id, SUM(COALESCE(total_cost, current_stock * COALESCE(cost, 0), 0)) as inventory_value')
            ->groupBy('branch_id')
            ->pluck('inventory_value', 'branch_id');

        return collect($branches)
            ->map(fn (Branch $branch): array => [
                'name' => $branch->name,
                'total' => (float) ($values[$branch->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $branchIds
     * @return array<int, array{name:string,detected:int,received:int}>
     */
    private function invoiceTrend(array $branchIds): array
    {
        $start = CarbonImmutable::today()->subDays(6);

        $detected = PurchaseInvoice::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->where('created_at', '>=', $start->startOfDay())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('aggregate', 'day');

        $received = PurchaseInvoice::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['received_ok', 'received_discrepancy', 'closed'])
            ->where('updated_at', '>=', $start->startOfDay())
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as aggregate')
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->pluck('aggregate', 'day');

        return collect(range(0, 6))
            ->map(function (int $offset) use ($start, $detected, $received): array {
                $date = $start->addDays($offset);
                $key = $date->toDateString();

                return [
                    'name' => $date->locale('es')->isoFormat('ddd'),
                    'detected' => (int) ($detected[$key] ?? 0),
                    'received' => (int) ($received[$key] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @param  array<int, int>  $branchIds
     * @return array<int, array{id:int,name:string,code:string,qty:string,value:float,unit:string|null}>
     */
    private function topProducts(array $branchIds): array
    {
        return InventoryProduct::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->select(['id', 'code', 'name', 'unit', 'current_stock', 'total_cost', 'cost'])
            ->orderByRaw('COALESCE(total_cost, current_stock * COALESCE(cost, 0), 0) DESC')
            ->limit(5)
            ->get()
            ->map(fn (InventoryProduct $product): array => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'qty' => number_format((float) $product->current_stock, 2),
                'value' => (float) ($product->total_cost ?? (((float) $product->current_stock) * ((float) ($product->cost ?? 0)))),
                'unit' => $product->unit,
            ])
            ->all();
    }

    /**
     * @param  array<int, int>  $branchIds
     * @return array<int, array{status:string,label:string,count:int}>
     */
    private function receptionStatus(array $branchIds): array
    {
        $counts = PurchaseInvoice::withoutBranchScope()
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect([
            'awaiting_physical' => 'Esperando bodega',
            'receiving' => 'En recepcion',
            'received_ok' => 'Conforme',
            'received_discrepancy' => 'Con novedad',
            'closed' => 'Cerrado',
        ])->map(fn (string $label, string $status): array => [
            'status' => $status,
            'label' => $label,
            'count' => (int) ($counts[$status] ?? 0),
        ])->values()->all();
    }
}
