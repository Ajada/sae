<?php 

namespace App\Services\Recurring;

use App\Models\MessageSchedule;
use Carbon\Carbon;

class RecurringScheduleService
{
    public function calculateNextRun(MessageSchedule $schedule, ?Carbon $from = null): ?Carbon
    {
        if (! in_array($schedule->status, ['active'], true)) {
            return null;
        }

        $timezone = $schedule->timezone ?: 'America/Sao_Paulo';

        $from = $from
            ? $from->copy()->timezone($timezone)
            : now($timezone);

        if ($schedule->starts_at && $schedule->starts_at->timezone($timezone)->greaterThan($from)) {
            $from = $schedule->starts_at->copy()->timezone($timezone);
        }

        $candidate = match ($schedule->frequency) {
            'daily' => $this->nextDaily($schedule, $from, $timezone),
            'weekly' => $this->nextWeekly($schedule, $from, $timezone),
            'monthly' => $this->nextMonthly($schedule, $from, $timezone),
            'yearly' => $this->nextYearly($schedule, $from, $timezone),
            default => null,
        };

        if (! $candidate) {
            return null;
        }

        if ($schedule->ends_at && $candidate->greaterThan($schedule->ends_at->copy()->timezone($timezone))) {
            return null;
        }

        return $candidate->utc();
    }

    private function nextDaily(MessageSchedule $schedule, Carbon $from, string $timezone): Carbon
    {
        $candidate = $this->withSendTime($from->copy(), $schedule->send_time, $timezone);

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function nextWeekly(MessageSchedule $schedule, Carbon $from, string $timezone): Carbon
    {
        $dayOfWeek = (int) $schedule->day_of_week;

        $candidate = $this->withSendTime($from->copy(), $schedule->send_time, $timezone);

        while ($candidate->dayOfWeekIso !== $dayOfWeek) {
            $candidate->addDay();
        }

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate->addWeek();
        }

        return $candidate;
    }

    private function nextMonthly(MessageSchedule $schedule, Carbon $from, string $timezone): Carbon
    {
        $candidate = $this->monthlyCandidate(
            year: (int) $from->year,
            month: (int) $from->month,
            day: (int) $schedule->day_of_month,
            sendTime: $schedule->send_time,
            timezone: $timezone
        );

        if ($candidate->lessThanOrEqualTo($from)) {
            $next = $from->copy()->addMonthNoOverflow();

            $candidate = $this->monthlyCandidate(
                year: (int) $next->year,
                month: (int) $next->month,
                day: (int) $schedule->day_of_month,
                sendTime: $schedule->send_time,
                timezone: $timezone
            );
        }

        return $candidate;
    }

    private function nextYearly(MessageSchedule $schedule, Carbon $from, string $timezone): Carbon
    {
        $candidate = $this->monthlyCandidate(
            year: (int) $from->year,
            month: (int) $schedule->month,
            day: (int) $schedule->day_of_month,
            sendTime: $schedule->send_time,
            timezone: $timezone
        );

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate = $this->monthlyCandidate(
                year: (int) $from->year + 1,
                month: (int) $schedule->month,
                day: (int) $schedule->day_of_month,
                sendTime: $schedule->send_time,
                timezone: $timezone
            );
        }

        return $candidate;
    }

    private function monthlyCandidate(
        int $year,
        int $month,
        int $day,
        string $sendTime,
        string $timezone
    ): Carbon {
        $base = Carbon::create($year, $month, 1, 0, 0, 0, $timezone);
        $safeDay = min($day, $base->daysInMonth);

        $candidate = Carbon::create($year, $month, $safeDay, 0, 0, 0, $timezone);

        return $this->withSendTime($candidate, $sendTime, $timezone);
    }

    private function withSendTime(Carbon $date, string $sendTime, string $timezone): Carbon
    {
        [$hour, $minute] = explode(':', substr($sendTime, 0, 5));

        return $date
            ->timezone($timezone)
            ->setTime((int) $hour, (int) $minute, 0);
    }
}
