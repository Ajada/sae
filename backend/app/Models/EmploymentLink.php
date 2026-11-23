<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmploymentLink extends Model
{
    protected $fillable = [
        'company_id', 'managed_company_id', 'employee_id', 'registration',
        'job_title', 'department', 'admitted_at', 'terminated_at', 'is_away', 'away_since',
    ];

    protected $casts = [
        'admitted_at' => 'date:Y-m-d', 'terminated_at' => 'date:Y-m-d',
        'away_since' => 'date:Y-m-d', 'is_away' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn (self $link) => $link->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function employee() { return $this->belongsTo(Employee::class); }
    public function managedCompany() { return $this->belongsTo(ManagedCompany::class); }
}
