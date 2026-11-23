<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Aso extends Model
{
    protected $fillable = ['company_id', 'managed_company_id', 'employment_link_id', 'exam_type',
        'performed_at', 'next_due_at', 'esocial_submitted', 'esocial_submitted_at',
        'receipt_number', 'notes', 'created_by'];

    protected $casts = ['performed_at' => 'date:Y-m-d', 'next_due_at' => 'date:Y-m-d',
        'esocial_submitted' => 'boolean', 'esocial_submitted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn (self $aso) => $aso->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function employmentLink() { return $this->belongsTo(EmploymentLink::class); }
}
