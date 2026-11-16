<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReminderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'title',
        'description',
        'client_name',
        'category',
        'issued_by',
        'reviewed_by',
        'status',
        'archived_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ReminderItem $item) {
            if (! $item->uuid) {
                $item->uuid = (string) Str::uuid();
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

    public function dueDates()
    {
        return $this->hasMany(ReminderDueDate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
