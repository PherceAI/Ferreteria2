<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\Encryptable;
use Illuminate\Database\Eloquent\Model;

final class GmailOAuthToken extends Model
{
    use Auditable, Encryptable;

    protected $table = 'pherce_intel.gmail_oauth_tokens';

    protected $fillable = [
        'email',
        'access_token',
        'refresh_token',
        'token_type',
        'is_active',
        'expires_at',
        'connected_at',
        'last_used_at',
    ];

    protected array $encryptable = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
