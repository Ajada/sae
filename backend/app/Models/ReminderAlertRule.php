<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReminderAlertRule extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'reminder_due_date_id',
        'type',
        'days_before',
        'exact_date',
        'notify_on_due_date',
        'repeat_after_due',
        'repeat_interval_days',
        'repeat_until_resolved',
        'expires_after_days',
        'is_active',
    ];

    protected $casts = [
        'exact_date' => 'date',
        'notify_on_due_date' => 'boolean',
        'repeat_after_due' => 'boolean',
        'repeat_until_resolved' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReminderAlertRule $rule) {
            if (! $rule->uuid) {
                $rule->uuid = (string) Str::uuid();
            }
        });
    }

    public function dueDate()
    {
        return $this->belongsTo(ReminderDueDate::class, 'reminder_due_date_id');
    }

    public function dispatches()
    {
        return $this->hasMany(ReminderAlertDispatch::class);
    }

    
}
