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
            'supplier' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:160'],
            'subcategory' => ['nullable', 'string', 'max:160'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $branchId = (int) Context::get('branch_id');
        $search = trim((string) ($validated['search'] ?? ''));
        $supplier = trim((string) ($validated['supplier'] ?? ''));
        $category = trim((string) ($validated['category'] ?? ''));
        $subcategory = trim((string) ($validated['subcategory'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 50);

        $products = $this->products($branchId, $search, $supplier, $category, $subcategory, $perPage);

        return Inertia::render('inventory/products/index', [
            'products' => $products,
            'stats' => fn () => $this->stats($branchId),
            'filterOptions' => fn () => $this->filterOptions($branchId),
            'filters' => [
                'search' => $search,
                'supplier' => $supplier,
                'category' => $category,
                'subcategory' => $subcategory,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function products(
        int $branchId,
        string $search,
        string $supplier,
        string $category,
        string $subcategory,
        int $perPage,
    ): LengthAwarePaginator {
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
                'last_purchase_cost',
                'total_cost',
                'supplier_name',
                'category_name',
                'subcategory_name',
                'min_stock',
                'inventory_updated_at',
                'valued_inventory_updated_at',
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $needle = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($needle): void {
                    $query
                        ->whereRaw('LOWER(code) LIKE ?', [$needle])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
                });
            })
            ->when($supplier !== '', fn ($query) => $query->where('supplier_name', $supplier))
            ->when($category !== '', fn ($query) => $query->where('category_name', $category))
            ->when($subcategory !== '', fn ($query) => $query->where('subcategory_name', $subcategory))
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
                'last_purchase_cost' => $product->last_purchase_cost !== null ? (float) $product->last_purchase_cost : null,
                'total_cost' => $product->total_cost !== null ? (float) $product->total_cost : null,
                'supplier_name' => $product->supplier_name,
                'category_name' => $product->category_name,
                'subcategory_name' => $product->subcategory_name,
                'min_stock' => (float) $product->min_stock,
                'inventory_updated_at' => $product->inventory_updated_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
                'valued_inventory_updated_at' => $product->valued_inventory_updated_at?->timezone('America/Guayaquil')->format('d/m/Y H:i'),
            ]);
    }

    /**
     * @return array{total:int, low_stock:int, zero_stock:int, without_price:int, valued:int, inventory_value:float}
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
                    'valued' => (clone $query)->whereNotNull('total_cost')->count(),
                    'inventory_value' => (float) (clone $query)->sum('total_cost'),
                ];
            });
    }

    /**
     * @return array{suppliers:array<int,string>, categories:array<int,string>, subcategories:array<int,string>}
     */
    private function filterOptions(int $branchId): array
    {
        return Cache::tags(['inventory-products', "branch:{$branchId}"])
            ->remember("inventory-products:filters:{$branchId}", 300, fn (): array => [
                'suppliers' => $this->distinctFilterValues($branchId, 'supplier_name'),
                'categories' => $this->distinctFilterValues($branchId, 'category_name'),
                'subcategories' => $this->distinctFilterValues($branchId, 'subcategory_name'),
            ]);
    }

    /**
     * @return array<int,string>
     */
    private function distinctFilterValues(int $branchId, string $column): array
    {
        return InventoryProduct::query()
            ->forBranch($branchId)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->distinct()
            ->orderBy($column)
            ->limit(300)
            ->pluck($column)
            ->values()
            ->all();
    }
}
