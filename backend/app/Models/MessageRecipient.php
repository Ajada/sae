<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageRecipient extends Model
{
    protected $fillable = [
        'message_id',
        'contact_id',
        'name',
        'email',
        'phone',
        'status',
        'sent_at',
        'failed_at',
        'skipped_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'skipped_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
