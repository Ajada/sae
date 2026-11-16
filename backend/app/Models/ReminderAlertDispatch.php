<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReminderAlertDispatch extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'reminder_due_date_id',
        'reminder_alert_rule_id',
        'message_id',
        'trigger_date',
        'status',
        'generated_at',
        'queued_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'trigger_date' => 'date',
        'generated_at' => 'datetime',
        'queued_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReminderAlertDispatch $dispatch) {
            if (! $dispatch->uuid) {
                $dispatch->uuid = (string) Str::uuid();
            }
        });
    }

    public function dueDate()
    {
        return $this->belongsTo(ReminderDueDate::class, 'reminder_due_date_id');
    }

    public function alertRule()
    {
        return $this->belongsTo(ReminderAlertRule::class, 'reminder_alert_rule_id');
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
