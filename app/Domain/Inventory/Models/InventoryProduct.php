<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\Branch;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryProduct extends Model
{
    use Auditable, BranchScoped;

    protected $table = 'pherce_intel.inventory_products';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'unit',
        'current_stock',
        'cost',
        'sale_price',
        'last_purchase_cost',
        'total_cost',
        'supplier_code',
        'supplier_name',
        'category_code',
        'category_name',
        'subcategory_code',
        'subcategory_name',
        'min_stock',
        'inventory_updated_at',
        'valued_inventory_updated_at',
        'import_source',
        'source_row',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'cost' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'last_purchase_cost' => 'decimal:4',
            'total_cost' => 'decimal:4',
            'min_stock' => 'decimal:3',
            'inventory_updated_at' => 'datetime',
            'valued_inventory_updated_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
