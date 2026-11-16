<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderDueDate;
use App\Services\ActivityLogger;
use App\Services\Reminders\ReminderActionTokenService;
use App\Services\Reminders\ReminderStatusService;
use Illuminate\Http\Request;
use Throwable;

class PublicReminderActionController extends Controller
{
    public function resolveByToken(
        Request $request,
        ReminderActionTokenService $tokenService,
        ReminderStatusService $statusService,
        ActivityLogger $logger
    ) {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $token = $tokenService->resolveToken($validated['token']);

            $dueDate = ReminderDueDate::query()
                ->where('id', $token->source_id)
                ->where('company_id', $token->company_id)
                ->firstOrFail();

            if ($dueDate->status === 'resolved') {
                $token->update([
                    'used_at' => now(),
                ]);

                return response()->json([
                    'message' => 'Este vencimento já estava resolvido.',
                    'due_date' => $dueDate,
                ]);
            }

            if (in_array($dueDate->status, ['renewed', 'cancelled'], true)) {
                return response()->json([
                    'message' => 'Este vencimento não pode ser resolvido no status atual.',
                ], 422);
            }

            $oldValues = $dueDate->toArray();

            $dueDate->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => null,
                'updated_by' => null,
            ]);

            $token->update([
                'used_at' => now(),
            ]);

            $statusService->refreshItemStatus($dueDate->reminderItem);

            $logger->log(
                action: 'reminder_due_date.resolved_by_email',
                subject: $dueDate,
                oldValues: $oldValues,
                newValues: [
                    ...$dueDate->fresh()->toArray(),
                    'recipient_email' => $token->recipient_email,
                ],
                companyId: $dueDate->company_id,
                userId: null
            );

            return response()->json([
                'message' => 'Vencimento marcado como resolvido com sucesso.',
                'due_date' => $dueDate->fresh(),
            ]);
        } catch (Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
