<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReminderAlertDispatch;
use App\Models\ReminderAlertRule;
use App\Models\ReminderDueDate;
use App\Services\Reminders\ReminderMessageFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckReminderDueDatesCommand extends Command
{
    protected $signature = 'xflow:check-reminder-due-dates';

    protected $description = 'Verifica vencimentos e gera mensagens automáticas para regras aplicáveis.';

    public function handle(ReminderMessageFactory $messageFactory): int
    {
        $today = now()->startOfDay();

        $this->updateExpiredDueDates($today);

        $rules = ReminderAlertRule::query()
            ->where('is_active', true)
            ->with([
                'dueDate.reminderItem.company',
                'dueDate.recipients',
                'dueDate.template',
                'dueDate.provider',
            ])
            ->whereHas('dueDate', function ($query) {
                $query->whereNotIn('status', ['resolved', 'renewed', 'cancelled'])
                    ->whereHas('reminderItem', function ($q) {
                        $q->whereNotIn('status', ['archived', 'completed', 'inactive'])
                            ->whereNull('archived_at');
                    });
            })
            ->get();

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rules as $rule) {
            $dueDate = $rule->dueDate;

            if (! $dueDate) {
                $skipped++;
                continue;
            }

            if (! $this->shouldTrigger($rule, $dueDate, $today)) {
                $skipped++;
                continue;
            }

            try {
                $dispatch = $this->createDispatchIfNotExists($rule, $dueDate, $today);

                if (! $dispatch->wasRecentlyCreated) {
                    $skipped++;
                    continue;
                }

                $messageFactory->createFromDueDate(
                    dueDate: $dueDate,
                    rule: $rule,
                    dispatch: $dispatch
                );

                $generated++;
            } catch (Throwable $exception) {
                $failed++;

                if (isset($dispatch)) {
                    $dispatch->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error_message' => $exception->getMessage(),
                    ]);
                }

                $this->error("Falha ao processar regra {$rule->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Mensagens geradas: {$generated}");
        $this->info("Regras ignoradas: {$skipped}");
        $this->info("Falhas: {$failed}");

        return self::SUCCESS;
    }

    private function shouldTrigger(
        ReminderAlertRule $rule,
        ReminderDueDate $dueDate,
        $today
    ): bool {
        $dueDateValue = $dueDate->due_date->copy()->startOfDay();

        return match ($rule->type) {
            'days_before' => $this->matchesDaysBefore($rule, $dueDateValue, $today),
            'exact_date' => $this->matchesExactDate($rule, $today),
            'on_due_date' => $this->matchesDueDate($dueDateValue, $today),
            'after_due_repeat' => $this->matchesAfterDueRepeat($rule, $dueDateValue, $today),
            default => false,
        };
    }

    private function matchesDaysBefore(ReminderAlertRule $rule, $dueDate, $today): bool
    {
        if ($rule->days_before === null) {
            return false;
        }

        return $dueDate->copy()
            ->subDays($rule->days_before)
            ->isSameDay($today);
    }

    private function matchesExactDate(ReminderAlertRule $rule, $today): bool
    {
        if (! $rule->exact_date) {
            return false;
        }

        return $rule->exact_date->copy()
            ->startOfDay()
            ->isSameDay($today);
    }

    private function matchesDueDate($dueDate, $today): bool
    {
        return $dueDate->isSameDay($today);
    }

    private function matchesAfterDueRepeat(ReminderAlertRule $rule, $dueDate, $today): bool
    {
        if (! $rule->repeat_after_due || ! $rule->repeat_interval_days) {
            return false;
        }

        if ($today->lessThanOrEqualTo($dueDate)) {
            return false;
        }

        $daysOverdue = $dueDate->diffInDays($today);

        if ($rule->expires_after_days && $daysOverdue > $rule->expires_after_days) {
            return false;
        }

        return $daysOverdue % $rule->repeat_interval_days === 0;
    }

    private function createDispatchIfNotExists(
        ReminderAlertRule $rule,
        ReminderDueDate $dueDate,
        $today
    ): ReminderAlertDispatch {
        return DB::transaction(function () use ($rule, $dueDate, $today) {
            return ReminderAlertDispatch::firstOrCreate(
                [
                    'reminder_due_date_id' => $dueDate->id,
                    'reminder_alert_rule_id' => $rule->id,
                    'trigger_date' => $today->toDateString(),
                ],
                [
                    'company_id' => $dueDate->company_id,
                    'status' => 'pending',
                ]
            );
        });
    }

    private function updateExpiredDueDates($today): void
    {
        ReminderDueDate::query()
            ->where('due_date', '<', $today->toDateString())
            ->where('status', 'active')
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }
}
