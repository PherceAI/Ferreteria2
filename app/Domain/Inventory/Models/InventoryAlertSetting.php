<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Models\Branch;
use App\Models\User;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryAlertSetting extends Model
{
    use Auditable, BranchScoped;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_PRODUCT = 'product';

    protected $table = 'pherce_intel.inventory_alert_settings';

    protected $fillable = [
        'branch_id',
        'scope_type',
        'scope_key',
        'scope_label',
        'settings',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'settings' => 'array',
            'updated_by' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
