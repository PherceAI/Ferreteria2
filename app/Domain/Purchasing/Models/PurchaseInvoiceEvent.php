<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Models\User;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseInvoiceEvent extends Model
{
    use Auditable, BranchScoped;

    protected $table = 'pherce_intel.purchase_invoice_events';

    protected $fillable = [
        'branch_id',
        'invoice_id',
        'reception_confirmation_id',
        'user_id',
        'type',
        'title',
        'body',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'invoice_id');
    }

    public function receptionConfirmation(): BelongsTo
    {
        return $this->belongsTo(ReceptionConfirmation::class, 'reception_confirmation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
