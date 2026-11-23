<?php 

namespace App\Services\Providers;

use App\Models\DeliveryProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SmtpTestService
{
    public function test(DeliveryProvider $provider, ?string $to = null): array
    {
        if (! $provider->isEmailSmtp()) {
            return [
                'success' => false,
                'message' => 'Este provedor não é um SMTP de e-mail.',
                'error' => 'Invalid provider type.',
            ];
        }

        $to = $to ?: $provider->from_email;

        if (! $to) {
            return [
                'success' => false,
                'message' => 'Nenhum e-mail de destino foi informado para teste.',
                'error' => 'Missing recipient.',
            ];
        }

        try {
            $this->configureDynamicMailer($provider);

            Mail::mailer('dynamic_smtp')->raw(
                "Este é um e-mail de teste do X Flow.\n\nSe você recebeu esta mensagem, o provedor SMTP foi configurado corretamente.",
                function ($message) use ($provider, $to) {
                    $message
                        ->to($to)
                        ->subject('Teste de SMTP - X Flow')
                        ->from($provider->from_email, $provider->from_name);

                    if ($provider->reply_to_email) {
                        $message->replyTo($provider->reply_to_email);
                    }
                }
            );

            $provider->update([
                'tested_at' => now(),
                'last_error' => null,
            ]);

            return [
                'success' => true,
                'message' => 'E-mail de teste enviado com sucesso.',
                'error' => null,
            ];
        } catch (Throwable $exception) {
            $provider->update([
                'last_error' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Falha ao enviar e-mail de teste.',
                'error' => $exception->getMessage(),
            ];
        }
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
    }
}
