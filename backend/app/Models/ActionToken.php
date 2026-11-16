<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActionToken extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'action',
        'token_hash',
        'source_type',
        'source_id',
        'recipient_email',
        'meta',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActionToken $token) {
            if (! $token->uuid) {
                $token->uuid = (string) Str::uuid();
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThan($this->expires_at);
    }

    public function isUsed(): bool
    {
        return ! is_null($this->used_at);
    }
}
