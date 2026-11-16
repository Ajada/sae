<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReminderDueDate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'reminder_item_id',
        'provider_id',
        'template_id',
        'label',
        'due_date',
        'channel',
        'status',
        'allow_fallback',
        'notes',
        'resolved_at',
        'resolved_by',
        'renewed_from_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
        'allow_fallback' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReminderDueDate $dueDate) {
            if (! $dueDate->uuid) {
                $dueDate->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function reminderItem()
    {
        return $this->belongsTo(ReminderItem::class);
    }

    public function provider()
    {
        return $this->belongsTo(DeliveryProvider::class, 'provider_id');
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function alertRules()
    {
        return $this->hasMany(ReminderAlertRule::class);
    }

    public function recipients()
    {
        return $this->hasMany(ReminderDueDateRecipient::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(ReminderDueDate::class, 'renewed_from_id');
    }

    public function dispatches()
    {
        return $this->hasMany(ReminderAlertDispatch::class);
    }
}
