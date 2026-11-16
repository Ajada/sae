<?php

namespace App\Services\Reminders;

use App\Models\ActionToken;
use App\Models\ReminderDueDate;
use Illuminate\Support\Str;
use RuntimeException;

class ReminderActionTokenService
{
    public function createResolveToken(
        ReminderDueDate $dueDate,
        ?string $recipientEmail = null,
        ?int $expiresInDays = 30
    ): string {
        $plainToken = Str::random(80);

        ActionToken::create([
            'company_id' => $dueDate->company_id,
            'action' => 'resolve_reminder_due_date',
            'token_hash' => hash('sha256', $plainToken),
            'source_type' => ReminderDueDate::class,
            'source_id' => $dueDate->id,
            'recipient_email' => $recipientEmail,
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            'meta' => [
                'due_date_uuid' => $dueDate->uuid,
                'reminder_item_id' => $dueDate->reminder_item_id,
            ],
        ]);

        return $plainToken;
    }

    public function resolveToken(string $plainToken): ActionToken
    {
        $token = ActionToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $token) {
            throw new RuntimeException('Token inválido.');
        }

        if ($token->isUsed()) {
            throw new RuntimeException('Este token já foi utilizado.');
        }

        if ($token->isExpired()) {
            throw new RuntimeException('Este token expirou.');
        }

        if ($token->action !== 'resolve_reminder_due_date') {
            throw new RuntimeException('Ação inválida para este token.');
        }

        return $token;
    }

    public function buildResolveUrl(ReminderDueDate $dueDate, ?string $recipientEmail = null): string
    {
        $token = $this->createResolveToken($dueDate, $recipientEmail);

        $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url')), '/');

        return "{$frontendUrl}/resolve-reminder?token={$token}";
    }
}
