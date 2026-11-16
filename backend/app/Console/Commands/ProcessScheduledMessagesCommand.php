<?php

namespace App\Console\Commands;

use App\Jobs\DispatchMessageJob;
use App\Models\Message;
use Illuminate\Console\Command;

class ProcessScheduledMessagesCommand extends Command
{
    protected $signature = 'xflow:process-scheduled-messages';

    protected $description = 'Processa mensagens agendadas do X Flow.';

    public function handle(): int
    {
        $messages = Message::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->limit(100)
            ->get();

        foreach ($messages as $message) {
            $message->update([
                'status' => 'pending',
            ]);

            DispatchMessageJob::dispatch($message->id)->onQueue('emails');
        }

        $this->info("Mensagens enviadas para fila: {$messages->count()}");

        return self::SUCCESS;
    }
}
