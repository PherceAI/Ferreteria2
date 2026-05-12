<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Models\User;
use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BranchTransferEvent extends Model
{
    use Auditable;

    protected $table = 'pherce_intel.branch_transfer_events';

    protected $fillable = [
        'branch_transfer_id',
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

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
