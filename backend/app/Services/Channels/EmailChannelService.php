<?php

namespace App\Services\Channels;

use App\Models\DeliveryProvider;
use App\Models\Message;
use App\Models\MessageRecipient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class EmailChannelService
{
    public function send(
        Message $message,
        MessageRecipient $recipient,
        DeliveryProvider $provider
    ): void {
        $this->configureDynamicMailer($provider);

        $subject = $message->subject ?: 'Mensagem do X Flow';

        $html = $message->body_html ?: nl2br(e($message->body_text ?? ''));
        $text = $message->body_text ?: strip_tags($message->body_html ?? '');

        Mail::mailer('dynamic_smtp')->send([], [], function ($mail) use (
            $provider,
            $recipient,
            $subject,
            $html,
            $text
        ) {
            $mail->to($recipient->email, $recipient->name)
                ->subject($subject)
                ->from($provider->from_email, $provider->from_name);

            if ($provider->reply_to_email) {
                $mail->replyTo($provider->reply_to_email);
            }

            $mail->html($html);

            if ($text) {
                $mail->text($text);
            }
        });
    }

    private function configureDynamicMailer(DeliveryProvider $provider): void
    {
        Config::set('mail.mailers.dynamic_smtp', [
            'transport' => 'smtp',
            'host' => $provider->host,
            'port' => $provider->port,
            'encryption' => $provider->encryption,
            'username' => $provider->username,
            'password' => $provider->getDecryptedPassword(),
            'timeout' => 30,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ]);

        Config::set('mail.from.address', $provider->from_email);
        Config::set('mail.from.name', $provider->from_name);

        app('mail.manager')->forgetMailers();
    }
}