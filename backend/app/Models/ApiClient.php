<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'token_hash',
        'allowed_ips',
        'allowed_origins',
        'last_used_at',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'allowed_ips' => 'array',
        'allowed_origins' => 'array',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ApiClient $client) {
            if (! $client->uuid) {
                $client->uuid = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
