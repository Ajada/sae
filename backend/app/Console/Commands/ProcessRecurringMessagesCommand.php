<?php

namespace App\Console\Commands;

use App\Models\MessageSchedule;
use App\Models\MessageScheduleRun;
use App\Services\Recurring\RecurringMessageFactory;
use App\Services\Recurring\RecurringScheduleService;
use Illuminate\Console\Command;
use Throwable;

class ProcessRecurringMessagesCommand extends Command
{
    protected $signature = 'xflow:process-recurring-messages';

    protected $description = 'Processa disparos recorrentes do X Flow.';

    public function handle(
        RecurringMessageFactory $messageFactory,
        RecurringScheduleService $scheduleService
    ): int {
        $now = now();

        $schedules = MessageSchedule::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', $now)
            ->limit(100)
            ->get();

        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($schedules as $schedule) {
            try {
                $run = MessageScheduleRun::firstOrCreate(
                    [
                        'message_schedule_id' => $schedule->id,
                        'scheduled_for' => $schedule->next_run_at,
                    ],
                    [
                        'company_id' => $schedule->company_id,
                        'status' => 'pending',
                    ]
                );

                if (! $run->wasRecentlyCreated) {
                    $skipped++;

                    $schedule->update([
                        'next_run_at' => $scheduleService->calculateNextRun($schedule, now()),
                    ]);

                    continue;
                }

                $messageFactory->generate($schedule, $run);

                $generated++;
            } catch (Throwable $exception) {
                $failed++;

                if (isset($run)) {
                    $run->update([
                        'status' => 'failed',
                        'failed_at' => now(),
                        'error_message' => $exception->getMessage(),
                    ]);
                }

                $this->error("Falha no disparo recorrente {$schedule->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Recorrências geradas: {$generated}");
        $this->info("Recorrências ignoradas: {$skipped}");
        $this->info("Falhas: {$failed}");

        return self::SUCCESS;
    }
}
