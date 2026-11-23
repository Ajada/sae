<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
     /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'current_company_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'is_owner', 'joined_at'])
            ->withTimestamps();
    }

    public function currentCompany()
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function belongsToCompany(int $companyId): bool
    {
        return $this->companies()
            ->where('companies.id', $companyId)
            ->exists();
    }
}
