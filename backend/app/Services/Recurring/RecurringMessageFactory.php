<?php 

namespace App\Services\Recurring;

use App\Jobs\DispatchMessageJob;
use App\Models\Message;
use App\Models\MessageSchedule;
use App\Models\MessageScheduleRun;
use App\Services\Messages\RecipientResolverService;
use Illuminate\Support\Facades\DB;

class RecurringMessageFactory
{
    public function __construct(
        private RecipientResolverService $recipientResolver,
        private RecurringScheduleService $scheduleService
    ) {}

    public function generate(MessageSchedule $schedule, MessageScheduleRun $run): Message
    {
        return DB::transaction(function () use ($schedule, $run) {
            $schedule->load(['recipients', 'template', 'provider']);

            $recipientPayload = $this->buildRecipientPayload($schedule);

            $recipients = $this->recipientResolver->resolve(
                data: $recipientPayload,
                companyId: $schedule->company_id,
                channel: $schedule->channel
            );

            if ($recipients->isEmpty()) {
                throw new \RuntimeException('Nenhum destinatário válido encontrado para esta recorrência.');
            }

            $message = Message::create([
                'company_id' => $schedule->company_id,
                'provider_id' => $schedule->provider_id,
                'template_id' => $schedule->template_id,
                'channel' => $schedule->channel,

                'source_type' => 'message_schedule',
                'source_id' => $schedule->id,
                'external_id' => null,
                'idempotency_key' => "schedule-{$schedule->id}-{$run->scheduled_for->timestamp}",

                'subject' => $schedule->subject,
                'body_html' => $schedule->body_html,
                'body_text' => $schedule->body_text,

                'variables' => $this->buildVariables($schedule, $run),
                'metadata' => [
                    ...($schedule->metadata ?? []),
                    'message_schedule_id' => $schedule->id,
                    'message_schedule_uuid' => $schedule->uuid,
                    'scheduled_for' => $run->scheduled_for?->toDateTimeString(),
                ],

                'scheduled_at' => null,
                'status' => 'pending',
                'allow_fallback' => $schedule->allow_fallback,
                'created_by' => null,
            ]);

            foreach ($recipients as $recipient) {
                $message->recipients()->create($recipient);
            }

            $run->update([
                'message_id' => $message->id,
                'status' => 'generated',
                'generated_at' => now(),
                'error_message' => null,
            ]);

            DispatchMessageJob::dispatch($message->id)->onQueue('emails');

            $run->update([
                'status' => 'queued',
                'queued_at' => now(),
            ]);

            $schedule->update([
                'last_generated_at' => now(),
                'next_run_at' => $this->scheduleService->calculateNextRun($schedule, now()),
            ]);

            return $message;
        });
    }

    private function buildRecipientPayload(MessageSchedule $schedule): array
    {
        $contactIds = [];
        $contactListIds = [];
        $manualRecipients = [];

        foreach ($schedule->recipients as $recipient) {
            if ($recipient->type === 'contact' && $recipient->contact_id) {
                $contactIds[] = $recipient->contact_id;
            }

            if ($recipient->type === 'contact_list' && $recipient->contact_list_id) {
                $contactListIds[] = $recipient->contact_list_id;
            }

            if ($recipient->type === 'manual') {
                $manualRecipients[] = [
                    'name' => $recipient->name,
                    'email' => $recipient->email,
                    'phone' => $recipient->phone,
                ];
            }
        }

        return [
            'contact_ids' => array_values(array_unique($contactIds)),
            'contact_list_ids' => array_values(array_unique($contactListIds)),
            'recipients' => $manualRecipients,
        ];
    }

    private function buildVariables(MessageSchedule $schedule, MessageScheduleRun $run): array
    {
        return [
            ...($schedule->variables ?? []),
            'schedule_name' => $schedule->name,
            'frequency' => $schedule->frequency,
            'scheduled_for' => $run->scheduled_for?->format('d/m/Y H:i'),
        ];
    }
}
