<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MessageSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'provider_id',
        'template_id',
        'name',
        'channel',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'metadata',
        'frequency',
        'day_of_week',
        'day_of_month',
        'month',
        'send_time',
        'timezone',
        'starts_at',
        'ends_at',
        'last_generated_at',
        'next_run_at',
        'status',
        'allow_fallback',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'last_generated_at' => 'datetime',
        'next_run_at' => 'datetime',
        'allow_fallback' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (MessageSchedule $schedule) {
            if (! $schedule->uuid) {
                $schedule->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function recipients()
    {
        return $this->hasMany(MessageScheduleRecipient::class);
    }

    public function runs()
    {
        return $this->hasMany(MessageScheduleRun::class);
    }

    public function provider()
    {
        return $this->belongsTo(DeliveryProvider::class, 'provider_id');
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }
}
