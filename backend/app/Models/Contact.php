<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'company_id',
        'name',
        'email',
        'phone',
        'company_name',
        'role',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            if (! $contact->uuid) {
                $contact->uuid = (string) Str::uuid();
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

    public function lists()
    {
        return $this->belongsToMany(
            ContactList::class,
            'contact_list_members',
            'contact_id',
            'contact_list_id'
        )->withTimestamps();
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
