<?php

declare(strict_types=1);

namespace App\Domain\Warehouse\Models;

use App\Models\Branch;
use App\Models\User;
use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BranchTransfer extends Model
{
    use Auditable;

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY_TO_SHIP = 'ready_to_ship';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_RECEIVED_DISCREPANCY = 'received_discrepancy';

    public const STATUS_TINI_COMPLETED = 'tini_completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'pherce_intel.branch_transfers';

    protected $fillable = [
        'source_branch_id',
        'destination_branch_id',
        'requested_by',
        'request_key',
        'prepared_by',
        'shipped_by',
        'received_by',
        'tini_completed_by',
        'cancelled_by',
        'status',
        'request_notes',
        'preparation_notes',
        'shipping_notes',
        'reception_notes',
        'cancellation_reason',
        'preparing_at',
        'ready_to_ship_at',
        'shipped_at',
        'received_at',
        'tini_completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'preparing_at' => 'datetime',
            'ready_to_ship_at' => 'datetime',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
            'tini_completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BranchTransferItem::class, 'branch_transfer_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(BranchTransferEvent::class, 'branch_transfer_id')
            ->latest();
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_REQUESTED => 'Solicitado',
            self::STATUS_PREPARING => 'Preparando',
            self::STATUS_READY_TO_SHIP => 'Listo para envio',
            self::STATUS_IN_TRANSIT => 'En envio',
            self::STATUS_RECEIVED => 'Recibido',
            self::STATUS_RECEIVED_DISCREPANCY => 'Recibido con novedad',
            self::STATUS_TINI_COMPLETED => 'Formalizado en TINI',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public static function statusOptions(): array
    {
        return collect(self::statusLabels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function statusLabel(string $status): string
    {
        return self::statusLabels()[$status] ?? $status;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasGlobalBranchAccess()) {
            return $query;
        }

        $branchIds = $user->branches()->pluck('branches.id');

        return $query->where(function (Builder $query) use ($branchIds): void {
            $query
                ->whereIn('source_branch_id', $branchIds)
                ->orWhereIn('destination_branch_id', $branchIds);
        });
    }
}
