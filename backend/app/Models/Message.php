<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'provider_id',
        'template_id',
        'channel',
        'source_type',
        'source_id',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'scheduled_at',
        'started_at',
        'sent_at',
        'failed_at',
        'status',
        'allow_fallback',
        'created_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'allow_fallback' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            if (! $message->uuid) {
                $message->uuid = (string) Str::uuid();
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

    public function provider()
    {
        return $this->belongsTo(DeliveryProvider::class, 'provider_id');
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function recipients()
    {
        return $this->hasMany(MessageRecipient::class);
    }

    public function logs()
    {
        return $this->hasMany(MessageLog::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isFuture();
    }
}
