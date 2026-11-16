<?php

namespace App\Services\Messages;

use App\Models\MessageTemplate;

class MessageTemplateRenderer
{
    public function renderTemplate(MessageTemplate $template, array $variables = []): array
    {
        return [
            'subject' => $this->renderString($template->subject, $variables),
            'body_html' => $this->renderString($template->body_html, $variables),
            'body_text' => $this->renderString($template->body_text, $variables),
        ];
    }

    public function renderString(?string $content, array $variables = []): ?string
    {
        if ($content === null) {
            return null;
        }

        foreach ($variables as $key => $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $content = str_replace(
                [
                    '{{ '.$key.' }}',
                    '{{'.$key.'}}',
                ],
                (string) $value,
                $content
            );
        }

        return $content;
    }
}