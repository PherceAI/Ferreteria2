<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Purchasing\Models\PurchaseInvoice;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

final class PurchasingController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($this->canViewPurchasing($request->user()), 403);

        $branchId = (int) Context::get('branch_id');

        $suggestions = InventoryProduct::withoutBranchScope()
            ->with('branch')
            ->where('branch_id', $branchId)
            ->where('min_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->orderBy('current_stock')
            ->orderBy('name')
            ->limit(30)
            ->get();

        $codes = $suggestions->pluck('code')->filter()->unique()->values();
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name', 'warehouse_code']);

        $stockByCode = InventoryProduct::withoutBranchScope()
            ->whereIn('code', $codes)
            ->get(['branch_id', 'code', 'current_stock'])
            ->groupBy('code');

        return Inertia::render('purchasing/index', [
            'stats' => [
                'activeSuggestions' => $suggestions->count(),
                'awaitingReceipts' => PurchaseInvoice::withoutBranchScope()
                    ->where('branch_id', $branchId)
                    ->whereIn('status', ['awaiting_physical', 'receiving'])
                    ->count(),
                'estimatedRestockValue' => $suggestions->sum(function (InventoryProduct $product): float {
                    $suggestedQty = $this->suggestedQuantity($product);

                    return $suggestedQty * (float) ($product->last_purchase_cost ?? $product->cost ?? 0);
                }),
            ],
            'suggestions' => $suggestions->map(fn (InventoryProduct $product): array => [
                'id' => (int) $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'branch' => $product->branch?->display_name ?? $product->branch?->name ?? 'Sucursal actual',
                'stock' => (float) $product->current_stock,
                'min' => (float) $product->min_stock,
                'suggestedQty' => $this->suggestedQuantity($product),
                'supplier' => $product->supplier_name ?: 'Sin proveedor',
                'lastPrice' => $product->last_purchase_cost !== null
                    ? (float) $product->last_purchase_cost
                    : ($product->cost !== null ? (float) $product->cost : null),
                'reason' => ((float) $product->current_stock) <= 0 ? 'Sin stock' : 'Bajo minimo',
            ])->values(),
            'branches' => $branches->map(fn (Branch $branch): array => [
                'id' => (int) $branch->id,
                'name' => $branch->warehouse_code
                    ? "{$branch->name} / Bodega {$branch->warehouse_code}"
                    : $branch->name,
            ])->values(),
            'stockMatrix' => $suggestions
                ->take(8)
                ->map(function (InventoryProduct $product) use ($branches, $stockByCode): array {
                    $rows = $stockByCode->get($product->code, collect());

                    return [
                        'code' => $product->code,
                        'name' => $product->name,
                        'total' => (float) $rows->sum('current_stock'),
                        'branches' => $branches->map(function (Branch $branch) use ($rows, $product): array {
                            $stock = (float) ($rows->firstWhere('branch_id', $branch->id)?->current_stock ?? 0);

                            return [
                                'branchId' => (int) $branch->id,
                                'stock' => $stock,
                                'low' => $branch->id === $product->branch_id
                                    ? $stock <= (float) $product->min_stock
                                    : $stock <= 0,
                            ];
                        })->values(),
                    ];
                })
                ->values(),
        ]);
    }

    private function suggestedQuantity(InventoryProduct $product): float
    {
        $minStock = (float) $product->min_stock;
        $currentStock = (float) $product->current_stock;

        return max($minStock, ($minStock * 2) - $currentStock);
    }

    private function canViewPurchasing(?User $user): bool
    {
        return $user instanceof User
            && ($user->hasGlobalBranchAccess() || $user->hasAnyRole(config('internal.purchasing_roles')));
    }
}
