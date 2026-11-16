<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MessageTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'key',
        'channel',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'is_system',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (MessageTemplate $template) {
            if (! $template->uuid) {
                $template->uuid = (string) Str::uuid();
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

    public function scopeAvailableForCompany($query, int $companyId)
    {
        return $query->where(function ($q) use ($companyId) {
            $q->whereNull('company_id')
                ->where('is_system', true)
                ->orWhere('company_id', $companyId);
        });
    }
}
