<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MessageScheduleRun extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'message_schedule_id',
        'message_id',
        'scheduled_for',
        'generated_at',
        'queued_at',
        'failed_at',
        'status',
        'error_message',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'generated_at' => 'datetime',
        'queued_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MessageScheduleRun $run) {
            if (! $run->uuid) {
                $run->uuid = (string) Str::uuid();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(MessageSchedule::class, 'message_schedule_id');
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}
