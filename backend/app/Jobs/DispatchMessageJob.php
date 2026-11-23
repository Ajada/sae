<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\Messages\MessageDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $messageId
    ) {
        $this->onQueue('emails');
    }

    public function handle(MessageDispatchService $dispatchService): void
    {
        $message = Message::query()->find($this->messageId);

        if (! $message) {
            return;
        }

        $dispatchService->dispatch($message);
    }
}
