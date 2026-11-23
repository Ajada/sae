<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenewReminderDueDateRequest;
use App\Http\Requests\ResolveReminderDueDateRequest;
use App\Models\ReminderDueDate;
use App\Services\ActivityLogger;
use App\Services\Reminders\ReminderStatusService;
use Illuminate\Support\Facades\DB;

class ReminderDueDateController extends Controller
{
    public function resolve(
        ResolveReminderDueDateRequest $request,
        ReminderDueDate $reminderDueDate,
        ActivityLogger $logger,
        ReminderStatusService $statusService
    ) {
        $this->ensureCompanyDueDate($reminderDueDate);

        if ($reminderDueDate->status === 'resolved') {
            return response()->json([
                'message' => 'Este vencimento já está resolvido.',
                'due_date' => $reminderDueDate,
            ]);
        }

        if (in_array($reminderDueDate->status, ['renewed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Este vencimento não pode ser resolvido no status atual.',
            ], 422);
        }

        $oldValues = $reminderDueDate->toArray();

        $reminderDueDate->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'notes' => $request->filled('notes')
                ? $request->input('notes')
                : $reminderDueDate->notes,
            'updated_by' => auth()->id(),
        ]);

        $statusService->refreshItemStatus($reminderDueDate->reminderItem);

        $logger->log(
            action: 'reminder_due_date.resolved',
            subject: $reminderDueDate,
            oldValues: $oldValues,
            newValues: $reminderDueDate->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Vencimento marcado como resolvido com sucesso.',
            'due_date' => $reminderDueDate->fresh(),
        ]);
    }

    public function renew(
        RenewReminderDueDateRequest $request,
        ReminderDueDate $reminderDueDate,
        ActivityLogger $logger,
        ReminderStatusService $statusService
    ) {
        $this->ensureCompanyDueDate($reminderDueDate);

        if (in_array($reminderDueDate->status, ['renewed', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Este vencimento não pode ser renovado no status atual.',
            ], 422);
        }

        $validated = $request->validated();
        $oldValues = $reminderDueDate->load(['alertRules', 'recipients'])->toArray();

        $newDueDate = DB::transaction(function () use ($reminderDueDate, $validated) {
            $reminderDueDate->update([
                'status' => 'renewed',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            $newDueDate = $reminderDueDate->reminderItem->dueDates()->create([
                'company_id' => $reminderDueDate->company_id,
                'provider_id' => $validated['provider_id'] ?? $reminderDueDate->provider_id,
                'template_id' => $validated['template_id'] ?? $reminderDueDate->template_id,
                'label' => $validated['label'] ?? $reminderDueDate->label,
                'due_date' => $validated['new_due_date'],
                'channel' => $reminderDueDate->channel,
                'status' => 'active',
                'allow_fallback' => $reminderDueDate->allow_fallback,
                'notes' => $validated['notes'] ?? null,
                'renewed_from_id' => $reminderDueDate->id,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if (($validated['copy_alert_rules'] ?? true) === true) {
                foreach ($reminderDueDate->alertRules as $rule) {
                    $newDueDate->alertRules()->create([
                        'company_id' => $newDueDate->company_id,
                        'type' => $rule->type,
                        'days_before' => $rule->days_before,
                        'exact_date' => null,
                        'notify_on_due_date' => $rule->notify_on_due_date,
                        'repeat_after_due' => $rule->repeat_after_due,
                        'repeat_interval_days' => $rule->repeat_interval_days,
                        'repeat_until_resolved' => $rule->repeat_until_resolved,
                        'expires_after_days' => $rule->expires_after_days,
                        'is_active' => $rule->is_active,
                    ]);
                }
            }

            if (($validated['copy_recipients'] ?? true) === true) {
                foreach ($reminderDueDate->recipients as $recipient) {
                    $newDueDate->recipients()->create([
                        'company_id' => $newDueDate->company_id,
                        'type' => $recipient->type,
                        'contact_id' => $recipient->contact_id,
                        'contact_list_id' => $recipient->contact_list_id,
                        'name' => $recipient->name,
                        'email' => $recipient->email,
                        'phone' => $recipient->phone,
                    ]);
                }
            }

            return $newDueDate;
        });

        $statusService->refreshItemStatus($reminderDueDate->reminderItem);

        $logger->log(
            action: 'reminder_due_date.renewed',
            subject: $reminderDueDate,
            oldValues: $oldValues,
            newValues: [
                'old_due_date' => $reminderDueDate->fresh()->toArray(),
                'new_due_date' => $newDueDate->load(['alertRules', 'recipients'])->toArray(),
            ]
        );

        return response()->json([
            'message' => 'Vencimento renovado com sucesso.',
            'old_due_date' => $reminderDueDate->fresh(),
            'new_due_date' => $newDueDate->load(['alertRules', 'recipients']),
        ], 201);
    }

    private function ensureCompanyDueDate(ReminderDueDate $dueDate): void
    {
        abort_if(
            $dueDate->company_id !== app('company.id'),
            404,
            'Vencimento não encontrado.'
        );
    }
}
