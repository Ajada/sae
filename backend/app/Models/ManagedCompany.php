<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ManagedCompany extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'corporate_name', 'trade_name', 'cnpj', 'risk_grade',
        'email', 'phone', 'is_active', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'risk_grade' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $company) {
            $company->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
