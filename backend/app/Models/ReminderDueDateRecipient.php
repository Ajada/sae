<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderDueDateRecipient extends Model
{
    protected $fillable = [
        'company_id',
        'reminder_due_date_id',
        'type',
        'contact_id',
        'contact_list_id',
        'name',
        'email',
        'phone',
    ];

    public function dueDate()
    {
        return $this->belongsTo(ReminderDueDate::class, 'reminder_due_date_id');
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
