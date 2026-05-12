<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Domain\Inventory\Models\InventoryProduct;
use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BranchTransferItem extends Model
{
    use Auditable;

    protected $table = 'pherce_intel.branch_transfer_items';

    protected $fillable = [
        'branch_transfer_id',
        'inventory_product_id',
        'product_code',
        'product_name',
        'unit',
        'source_stock_snapshot',
        'source_stock_verified',
        'requested_qty',
        'prepared_qty',
        'received_qty',
        'has_discrepancy',
        'preparation_notes',
        'reception_notes',
    ];

    protected function casts(): array
    {
        return [
            'source_stock_snapshot' => 'decimal:3',
            'source_stock_verified' => 'boolean',
            'requested_qty' => 'decimal:3',
            'prepared_qty' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'has_discrepancy' => 'boolean',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    public function inventoryProduct(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class, 'inventory_product_id');
    }
}
