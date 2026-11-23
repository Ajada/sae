<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TeamImportBatch extends Model
{
    protected $fillable = ['company_id','managed_company_id','user_id','original_name','stored_path','sha256','mapping','corrections','result','status','expires_at','committed_at'];
    protected $casts = ['mapping'=>'array','corrections'=>'array','result'=>'array','expires_at'=>'datetime','committed_at'=>'datetime'];
    protected static function booted(): void
    {
        static::creating(fn (self $batch) => $batch->uuid ??= (string) Str::uuid());
    }
    public function getRouteKeyName(): string { return 'uuid'; }
}
