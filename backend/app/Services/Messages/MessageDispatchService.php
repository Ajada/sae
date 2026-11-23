<?php

namespace App\Services\Messages;

use App\Models\Message;
use App\Models\MessageLog;
use App\Models\MessageRecipient;
use App\Services\Channels\EmailChannelService;
use App\Services\Messages\MessageTemplateRenderer;
use Throwable;

class MessageDispatchService
{
    public function __construct(
        private ProviderResolverService $providerResolver,
        private EmailChannelService $emailChannel,
        private MessageTemplateRenderer $renderer
    ) {}

    public function dispatch(Message $message): void
    {
        $message->load(['template', 'recipients']);

        if (! in_array($message->status, ['pending', 'scheduled', 'failed'], true)) {
            return;
        }

        $message->update([
            'status' => 'processing',
            'started_at' => now(),
            'failed_at' => null,
        ]);

        if ($message->template) {
            $rendered = $this->renderer->renderTemplate(
                template: $message->template,
                variables: $message->variables ?? []
            );

            $message->update([
                'subject' => $rendered['subject'] ?: $message->subject,
                'body_html' => $rendered['body_html'] ?: $message->body_html,
                'body_text' => $rendered['body_text'] ?: $message->body_text,
            ]);

            $message->refresh();
        }

        $provider = $this->providerResolver->resolve($message);

        foreach ($message->recipients as $recipient) {
            $this->sendToRecipient($message, $recipient, $provider);
        }

        $this->refreshMessageStatus($message);
    }

    private function sendToRecipient(
        Message $message,
        MessageRecipient $recipient,
        $provider
    ): void {
        if ($recipient->status === 'sent') {
            return;
        }

        if ($message->channel === 'email' && empty($recipient->email)) {
            $recipient->update([
                'status' => 'skipped',
                'skipped_at' => now(),
                'error_message' => 'Destinatário sem e-mail.',
            ]);

            MessageLog::create([
                'company_id' => $message->company_id,
                'message_id' => $message->id,
                'message_recipient_id' => $recipient->id,
                'provider_id' => $provider->id,
                'status' => 'skipped',
                'attempt' => 1,
                'error_message' => 'Destinatário sem e-mail.',
            ]);

            return;
        }

        try {
            if ($message->channel === 'email') {
                $this->emailChannel->send($message, $recipient, $provider);
            }

            $recipient->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            MessageLog::create([
                'company_id' => $message->company_id,
                'message_id' => $message->id,
                'message_recipient_id' => $recipient->id,
                'provider_id' => $provider->id,
                'status' => 'sent',
                'attempt' => 1,
            ]);
        } catch (Throwable $exception) {
            $recipient->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            MessageLog::create([
                'company_id' => $message->company_id,
                'message_id' => $message->id,
                'message_recipient_id' => $recipient->id,
                'provider_id' => $provider->id,
                'status' => 'failed',
                'attempt' => 1,
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function refreshMessageStatus(Message $message): void
    {
        $message->load('recipients');

        // TODO: refatorar
        $total = $message->recipients->count();
        $sent = $message->recipients->where('status', 'sent')->count();
        $failed = $message->recipients->where('status', 'failed')->count();
        $skipped = $message->recipients->where('status', 'skipped')->count();

        if ($total > 0 && $sent === $total) {
            $message->update([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
            ]);

            return;
        }

        if ($sent > 0 && ($failed > 0 || $skipped > 0)) {
            $message->update([
                'status' => 'partially_sent',
                'sent_at' => now(),
            ]);

            return;
        }

        if ($sent === 0 && ($failed > 0 || $skipped > 0)) {
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
            ]);

            return;
        }

        $message->update([
            'status' => 'failed',
            'failed_at' => now(),
        ]);
    }
}