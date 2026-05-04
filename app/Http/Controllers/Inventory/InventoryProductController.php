<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryProductController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $branchId = (int) Context::get('branch_id');
        $search = trim((string) ($validated['search'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);

        $products = $this->products($branchId, $search, $perPage);

        return Inertia::render('inventory/products/index', [
            'products' => $products,
            'stats' => fn () => $this->stats($branchId),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function products(int $branchId, string $search, int $perPage): LengthAwarePaginator
    {
        return InventoryProduct::query()
            ->forBranch($branchId)
            ->select([
                'id',
                'code',
                'name',
                'unit',
                'current_stock',
                'cost',
                'sale_price',
                'min_stock',
                'inventory_updated_at',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($needle): void {
                    $query
                        ->whereRaw('LOWER(code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (InventoryProduct $product): array => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'unit' => $product->unit,
                'current_stock' => (float) $product->current_stock,
                'cost' => $product->cost !== null ? (float) $product->cost : null,
                'sale_price' => $product->sale_price !== null ? (float) $product->sale_price : null,
                'min_stock' => (float) $product->min_stock,
                'inventory_updated_at' => $product->inventory_updated_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
            ]);
    }

    /**
     * @return array{total:int, low_stock:int, zero_stock:int, without_price:int}
     */
    private function stats(int $branchId): array
    {
        return Cache::tags(['inventory-products', "branch:{$branchId}"])
            ->remember("inventory-products:stats:{$branchId}", 60, function () use ($branchId): array {
                $query = InventoryProduct::query()->forBranch($branchId);

                return [
                    'total' => (clone $query)->count(),
                    'low_stock' => (clone $query)
                        ->where('min_stock', '>', 0)
                        ->whereColumn('current_stock', '<=', 'min_stock')
                        ->count(),
                    'zero_stock' => (clone $query)->where('current_stock', '<=', 0)->count(),
                    'without_price' => (clone $query)->whereNull('sale_price')->count(),
                ];
            });
    }
}
