<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageScheduleRecipient extends Model
{
    protected $fillable = [
        'company_id',
        'message_schedule_id',
        'type',
        'contact_id',
        'contact_list_id',
        'name',
        'email',
        'phone',
    ];

    public function schedule()
    {
        return $this->belongsTo(MessageSchedule::class, 'message_schedule_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactList()
    {
        return $this->belongsTo(ContactList::class);
    }
}
