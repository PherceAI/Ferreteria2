<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\BranchScoped;
use App\Shared\Traits\Encryptable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Supplier extends Model
{
    use Auditable, BranchScoped, Encryptable;

    protected $table = 'pherce_intel.suppliers';

    protected $fillable = [
        'branch_id',
        'ruc',
        'ruc_hash',
        'name',
        'email',
        'is_active',
    ];

    protected array $encryptable = [
        'ruc',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }
}
