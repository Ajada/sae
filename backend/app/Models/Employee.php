<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Employee extends Model
{
    protected $fillable = ['company_id', 'name', 'cpf'];

    protected static function booted(): void
    {
        static::creating(fn (self $employee) => $employee->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function links() { return $this->hasMany(EmploymentLink::class); }
}
