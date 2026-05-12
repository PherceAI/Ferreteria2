<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'active_branch_id', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable, TwoFactorAuthenticatable;

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_branch_id' => 'integer',
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function activeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'active_branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function canAccessBranch(int $branchId): bool
    {
        return $this->hasGlobalBranchAccess()
            || $this->branches()->whereKey($branchId)->exists();
    }

    public function hasGlobalBranchAccess(): bool
    {
        return $this->hasRole(config('internal.owner_roles', []))
            || $this->can('branches.view-all');
    }
}
