<?php

namespace App\Services\Reminders;

use App\Jobs\DispatchMessageJob;
use App\Models\Message;
use App\Models\ReminderAlertDispatch;
use App\Models\ReminderAlertRule;
use App\Models\ReminderDueDate;
use App\Services\Messages\RecipientResolverService;
use Illuminate\Support\Facades\DB;

class ReminderMessageFactory
{
    public function __construct(
        private RecipientResolverService $recipientResolver,
        private ReminderActionTokenService $tokenService
    ) {}

    public function createFromDueDate(
        ReminderDueDate $dueDate,
        ReminderAlertRule $rule,
        ReminderAlertDispatch $dispatch
    ): Message {
        return DB::transaction(function () use ($dueDate, $rule, $dispatch) {
            $dueDate->load([
                'reminderItem',
                'recipients',
                'template',
                'provider',
            ]);

            $recipientPayload = $this->buildRecipientPayload($dueDate);

            $recipients = $this->recipientResolver->resolve(
                data: $recipientPayload,
                companyId: $dueDate->company_id,
                channel: $dueDate->channel
            );

            if ($recipients->isEmpty()) {
                throw new \RuntimeException('Nenhum destinatário válido encontrado para o vencimento.');
            }

            $variables = $this->buildVariables($dueDate, $rule);

            $message = Message::create([
                'company_id' => $dueDate->company_id,
                'provider_id' => $dueDate->provider_id,
                'template_id' => $dueDate->template_id,
                'channel' => $dueDate->channel,

                'source_type' => 'reminder_due_date',
                'source_id' => $dueDate->id,

                'subject' => $this->defaultSubject($dueDate, $rule),
                'body_html' => $dueDate->template_id ? null : $this->defaultHtmlBody($dueDate, $rule),
                'body_text' => $dueDate->template_id ? null : $this->defaultTextBody($dueDate, $rule),

                'variables' => $variables,
                'scheduled_at' => null,
                'status' => 'pending',
                'allow_fallback' => $dueDate->allow_fallback,
                'created_by' => null,
            ]);

            foreach ($recipients as $recipient) {
                $message->recipients()->create($recipient);
            }

            $dispatch->update([
                'message_id' => $message->id,
                'status' => 'generated',
                'generated_at' => now(),
                'error_message' => null,
            ]);

            DispatchMessageJob::dispatch($message->id)->onQueue('emails');

            $dispatch->update([
                'status' => 'queued',
                'queued_at' => now(),
            ]);

            return $message;
        });
    }

    private function buildRecipientPayload(ReminderDueDate $dueDate): array
    {
        $contactIds = [];
        $contactListIds = [];
        $manualRecipients = [];

        foreach ($dueDate->recipients as $recipient) {
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

    private function buildVariables(ReminderDueDate $dueDate, ReminderAlertRule $rule): array
    {
        $item = $dueDate->reminderItem;
        $daysLeft = now()->startOfDay()->diffInDays($dueDate->due_date, false);

        return [
            'company_name' => $item->company?->name,
            'reminder_title' => $item->title,
            'reminder_description' => $item->description,
            'client_name' => $item->client_name,
            'category' => $item->category,

            'due_date_label' => $dueDate->label,
            'due_date' => $dueDate->due_date?->format('d/m/Y'),
            'days_left' => $daysLeft,
            'days_overdue' => $daysLeft < 0 ? abs($daysLeft) : 0,

            'issued_by' => $item->issued_by,
            'reviewed_by' => $item->reviewed_by,

            'rule_type' => $rule->type,

            'resolve_url' => $this->tokenService->buildResolveUrl($dueDate),
        ];
    }

    private function defaultSubject(ReminderDueDate $dueDate, ReminderAlertRule $rule): string
    {
        $item = $dueDate->reminderItem;
        $daysLeft = now()->startOfDay()->diffInDays($dueDate->due_date, false);

        if ($daysLeft < 0) {
            return "Vencido: {$item->title}";
        }

        if ($daysLeft === 0) {
            return "Vence hoje: {$item->title}";
        }

        return "Aviso: {$item->title} vence em {$daysLeft} dias";
    }

    private function defaultHtmlBody(ReminderDueDate $dueDate, ReminderAlertRule $rule): string
    {
        $item = $dueDate->reminderItem;
        $daysLeft = now()->startOfDay()->diffInDays($dueDate->due_date, false);

        return "
            <div style=\"font-family: Arial, sans-serif; line-height: 1.5; color: #111827;\">
                <h2>Aviso de vencimento</h2>

                <p>O item <strong>{$item->title}</strong> possui um vencimento programado.</p>

                <p>
                    <strong>Cliente:</strong> {$item->client_name}<br>
                    <strong>Categoria:</strong> {$item->category}<br>
                    <strong>Vencimento:</strong> {$dueDate->due_date->format('d/m/Y')}<br>
                    <strong>Dias restantes:</strong> {$daysLeft}<br>
                    <strong>Responsável pela emissão:</strong> {$item->issued_by}<br>
                    <strong>Responsável pela revisão:</strong> {$item->reviewed_by}
                </p>

                <p>{$item->description}</p>

                <p style=\"font-size: 12px; color: #6b7280;\">
                    Esta é uma mensagem automática enviada pelo X Flow.
                </p>
            </div>
        ";
    }

    private function defaultTextBody(ReminderDueDate $dueDate, ReminderAlertRule $rule): string
    {
        $item = $dueDate->reminderItem;
        $daysLeft = now()->startOfDay()->diffInDays($dueDate->due_date, false);

        return <<<TEXT
Aviso de vencimento

Item: {$item->title}
Cliente: {$item->client_name}
Categoria: {$item->category}
Vencimento: {$dueDate->due_date->format('d/m/Y')}
Dias restantes: {$daysLeft}
Responsável pela emissão: {$item->issued_by}
Responsável pela revisão: {$item->reviewed_by}

{$item->description}

Esta é uma mensagem automática enviada pelo X Flow.
TEXT;
    }
}
