<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReceptionConfirmationItem extends Model
{
    use Auditable;

    protected $table = 'pherce_intel.reception_confirmation_items';

    protected $fillable = [
        'confirmation_id',
        'purchase_invoice_item_id',
        'tini_product_id',
        'description',
        'expected_qty',
        'received_qty',
        'condition_status',
        'has_discrepancy',
        'discrepancy_notes',
    ];

    protected function casts(): array
    {
        return [
            'expected_qty' => 'decimal:2',
            'received_qty' => 'decimal:2',
            'has_discrepancy' => 'boolean',
        ];
    }

    public function confirmation(): BelongsTo
    {
        return $this->belongsTo(ReceptionConfirmation::class, 'confirmation_id');
    }

    public function purchaseInvoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class, 'purchase_invoice_item_id');
    }
}
