<?php

namespace App\Services\Reminders;

use App\Models\ReminderItem;

class ReminderStatusService
{
    public function refreshItemStatus(ReminderItem $item): void
    {
        $item->load('dueDates');

        $hasOpenDueDates = $item->dueDates()
            ->whereNotIn('status', ['resolved', 'renewed', 'cancelled'])
            ->exists();

        if (! $hasOpenDueDates && $item->status !== 'archived') {
            $item->update([
                'status' => 'completed',
                'updated_by' => auth()->id(),
            ]);
        }

        if ($hasOpenDueDates && $item->status === 'completed') {
            $item->update([
                'status' => 'active',
                'updated_by' => auth()->id(),
            ]);
        }
    }
}
