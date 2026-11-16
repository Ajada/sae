<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'legal_name',
        'document',
        'email',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (! $company->uuid) {
                $company->uuid = (string) Str::uuid();
            }
        });
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function apiClients()
    {
        return $this->hasMany(ApiClient::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
