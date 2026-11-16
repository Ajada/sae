<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    protected $fillable = [
        'company_id',
        'message_id',
        'message_recipient_id',
        'provider_id',
        'status',
        'attempt',
        'error_message',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function recipient()
    {
        return $this->belongsTo(MessageRecipient::class, 'message_recipient_id');
    }

    public function provider()
    {
        return $this->belongsTo(DeliveryProvider::class, 'provider_id');
    }
}
