<?php

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        MessageTemplate::updateOrCreate(
            [
                'company_id' => null,
                'key' => 'expiration_notice_default',
            ],
            [
                'name' => 'Aviso de vencimento padrão',
                'channel' => 'email',
                'subject' => 'Aviso: {{ reminder_title }} vence em {{ days_left }} dias',
                'body_html' => $this->expirationNoticeHtml(),
                'body_text' => $this->expirationNoticeText(),
                'variables' => [
                    'company_name',
                    'reminder_title',
                    'reminder_description',
                    'client_name',
                    'due_date',
                    'days_left',
                    'issued_by',
                    'reviewed_by',
                    'resolve_url',
                ],
                'is_system' => true,
                'is_active' => true,
            ]
        );

        MessageTemplate::updateOrCreate(
            [
                'company_id' => null,
                'key' => 'free_message_default',
            ],
            [
                'name' => 'Mensagem livre padrão',
                'channel' => 'email',
                'subject' => '{{ subject }}',
                'body_html' => '{{ message }}',
                'body_text' => '{{ message }}',
                'variables' => [
                    'subject',
                    'message',
                    'company_name',
                ],
                'is_system' => true,
                'is_active' => true,
            ]
        );
    }

    private function expirationNoticeHtml(): string
    {
        return <<<'HTML'
<div style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin-bottom: 8px;">Aviso de vencimento</h2>

    <p>Olá,</p>

    <p>
        O item <strong>{{ reminder_title }}</strong> está próximo do vencimento.
    </p>

    <p>
        <strong>Cliente:</strong> {{ client_name }}<br>
        <strong>Data de vencimento:</strong> {{ due_date }}<br>
        <strong>Dias restantes:</strong> {{ days_left }}<br>
        <strong>Responsável pela emissão:</strong> {{ issued_by }}<br>
        <strong>Responsável pela revisão:</strong> {{ reviewed_by }}
    </p>

    <p>
        {{ reminder_description }}
    </p>

    <p style="margin-top: 24px;">
        <a href="{{ resolve_url }}" style="background: #111827; color: #ffffff; padding: 12px 18px; text-decoration: none; border-radius: 6px;">
            Marcar como resolvido
        </a>
    </p>

    <p style="margin-top: 24px; font-size: 12px; color: #6b7280;">
        Esta é uma mensagem automática enviada pelo X Flow.
    </p>
</div>
HTML;
    }

    private function expirationNoticeText(): string
    {
        return <<<'TEXT'
Aviso de vencimento

O item {{ reminder_title }} está próximo do vencimento.

Cliente: {{ client_name }}
Data de vencimento: {{ due_date }}
Dias restantes: {{ days_left }}
Responsável pela emissão: {{ issued_by }}
Responsável pela revisão: {{ reviewed_by }}

{{ reminder_description }}

Marcar como resolvido:
{{ resolve_url }}

Esta é uma mensagem automática enviada pelo X Flow.
TEXT;
    }
}
