<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'code', 'warehouse_name', 'warehouse_code', 'address', 'city', 'is_headquarters', 'is_active'])]
class Branch extends Model
{
    use HasFactory;

    protected $appends = [
        'display_name',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_headquarters' => 'boolean',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        if ($this->warehouse_name) {
            return "{$this->name} / {$this->warehouse_name}";
        }

        return $this->name;
    }

    public function scopeSearchAlias($query, string $search)
    {
        return $query->where(function ($query) use ($search): void {
            $needle = "%{$search}%";

            $query
                ->where('name', 'like', $needle)
                ->orWhere('code', 'like', $needle)
                ->orWhere('warehouse_name', 'like', $needle)
                ->orWhere('warehouse_code', 'like', $needle);
        });
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }
}
