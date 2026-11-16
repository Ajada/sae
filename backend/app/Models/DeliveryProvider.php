<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class DeliveryProvider extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'channel',
        'driver',
        'host',
        'port',
        'username',
        'password_encrypted',
        'encryption',
        'from_name',
        'from_email',
        'reply_to_email',
        'is_default',
        'is_active',
        'priority',
        'tested_at',
        'last_error',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'tested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeliveryProvider $provider) {
            if (! $provider->uuid) {
                $provider->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getDecryptedPassword(): ?string
    {
        if (! $this->password_encrypted) {
            return null;
        }

        return Crypt::decryptString($this->password_encrypted);
    }

    public function isEmailSmtp(): bool
    {
        return $this->channel === 'email' && $this->driver === 'smtp';
    }
}
