<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PurchaseInvoice extends Model
{
    use Auditable, BranchScoped;

    protected $table = 'pherce_intel.purchase_invoices';

    protected $fillable = [
        'branch_id',
        'supplier_id',
        'invoice_number',
        'access_key',
        'emission_date',
        'total',
        'status',
        'gmail_message_id',
        'from_email',
    ];

    protected function casts(): array
    {
        return [
            'emission_date' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'invoice_id');
    }

    public function receptionConfirmation(): HasOne
    {
        return $this->hasOne(ReceptionConfirmation::class, 'invoice_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceEvent::class, 'invoice_id')
            ->latest();
    }
}
