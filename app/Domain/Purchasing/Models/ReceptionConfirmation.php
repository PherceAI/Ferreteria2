<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Models\User;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReceptionConfirmation extends Model
{
    use Auditable, BranchScoped;

    protected $table = 'pherce_intel.reception_confirmations';

    protected $fillable = [
        'branch_id',
        'invoice_id',
        'tini_invoice_id',
        'confirmed_by',
        'status',
        'notes',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceptionConfirmationItem::class, 'confirmation_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceEvent::class, 'reception_confirmation_id')
            ->latest();
    }
}
