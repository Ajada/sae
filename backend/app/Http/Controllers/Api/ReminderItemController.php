<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReminderItemRequest;
use App\Http\Requests\UpdateReminderItemRequest;
use App\Models\ReminderItem;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReminderItemController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $items = ReminderItem::query()
            ->where('company_id', $companyId)
            ->withCount('dueDates')
            ->with([
                'dueDates' => function ($query) {
                    $query->select(
                        'id',
                        'uuid',
                        'reminder_item_id',
                        'label',
                        'due_date',
                        'status'
                    )
                        ->orderBy('due_date');
                },
            ])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->string('category'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(
        StoreReminderItemRequest $request,
        ActivityLogger $logger
    ) {
        $companyId = app('company.id');
        $validated = $request->validated();

        $item = DB::transaction(function () use ($validated, $companyId) {
            $item = ReminderItem::create([
                'company_id' => $companyId,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'client_name' => $validated['client_name'] ?? null,
                'category' => $validated['category'] ?? null,
                'issued_by' => $validated['issued_by'] ?? null,
                'reviewed_by' => $validated['reviewed_by'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($validated['due_dates'] as $dueDateData) {
                $dueDate = $item->dueDates()->create([
                    'company_id' => $companyId,
                    'provider_id' => $dueDateData['provider_id'] ?? null,
                    'template_id' => $dueDateData['template_id'] ?? null,
                    'label' => $dueDateData['label'] ?? null,
                    'due_date' => $dueDateData['due_date'],
                    'channel' => $dueDateData['channel'] ?? 'email',
                    'status' => 'active',
                    'allow_fallback' => $dueDateData['allow_fallback'] ?? true,
                    'notes' => $dueDateData['notes'] ?? null,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);

                foreach ($dueDateData['alert_rules'] as $ruleData) {
                    $dueDate->alertRules()->create([
                        'company_id' => $companyId,
                        'type' => $ruleData['type'],
                        'days_before' => $ruleData['days_before'] ?? null,
                        'exact_date' => $ruleData['exact_date'] ?? null,
                        'notify_on_due_date' => $ruleData['notify_on_due_date']
                            ?? $ruleData['type'] === 'on_due_date',
                        'repeat_after_due' => $ruleData['repeat_after_due']
                            ?? $ruleData['type'] === 'after_due_repeat',
                        'repeat_interval_days' => $ruleData['repeat_interval_days'] ?? null,
                        'repeat_until_resolved' => $ruleData['repeat_until_resolved'] ?? true,
                        'expires_after_days' => $ruleData['expires_after_days'] ?? null,
                        'is_active' => $ruleData['is_active'] ?? true,
                    ]);
                }

                foreach ($dueDateData['recipients'] as $recipientData) {
                    $dueDate->recipients()->create([
                        'company_id' => $companyId,
                        'type' => $recipientData['type'],
                        'contact_id' => $recipientData['contact_id'] ?? null,
                        'contact_list_id' => $recipientData['contact_list_id'] ?? null,
                        'name' => $recipientData['name'] ?? null,
                        'email' => $recipientData['email'] ?? null,
                        'phone' => $recipientData['phone'] ?? null,
                    ]);
                }
            }

            return $item;
        });

        $item->load([
            'dueDates.alertRules',
            'dueDates.recipients',
            'dueDates.provider:id,uuid,name',
            'dueDates.template:id,uuid,name,key',
        ]);

        $logger->log(
            action: 'reminder_item.created',
            subject: $item,
            newValues: $item->toArray()
        );

        return response()->json([
            'message' => 'Item de vencimento cadastrado com sucesso.',
            'item' => $item,
        ], 201);
    }

    public function show(ReminderItem $reminderItem)
    {
        $this->ensureCompanyItem($reminderItem);

        return response()->json([
            'item' => $reminderItem->load([
                'dueDates' => function ($query) {
                    $query->orderBy('due_date');
                },
                'dueDates.alertRules',
                'dueDates.recipients.contact:id,uuid,name,email,phone',
                'dueDates.recipients.contactList:id,uuid,name',
                'dueDates.provider:id,uuid,name,from_email',
                'dueDates.template:id,uuid,name,key',
            ]),
        ]);
    }

    public function update(
        UpdateReminderItemRequest $request,
        ReminderItem $reminderItem,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyItem($reminderItem);

        $oldValues = $reminderItem->toArray();
        $validated = $request->validated();

        $reminderItem->update([
            'title' => $validated['title'] ?? $reminderItem->title,
            'description' => array_key_exists('description', $validated)
                ? $validated['description']
                : $reminderItem->description,
            'client_name' => array_key_exists('client_name', $validated)
                ? $validated['client_name']
                : $reminderItem->client_name,
            'category' => array_key_exists('category', $validated)
                ? $validated['category']
                : $reminderItem->category,
            'issued_by' => array_key_exists('issued_by', $validated)
                ? $validated['issued_by']
                : $reminderItem->issued_by,
            'reviewed_by' => array_key_exists('reviewed_by', $validated)
                ? $validated['reviewed_by']
                : $reminderItem->reviewed_by,
            'status' => $validated['status'] ?? $reminderItem->status,
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'reminder_item.updated',
            subject: $reminderItem,
            oldValues: $oldValues,
            newValues: $reminderItem->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Item de vencimento atualizado com sucesso.',
            'item' => $reminderItem->fresh(),
        ]);
    }

    public function destroy(ReminderItem $reminderItem, ActivityLogger $logger)
    {
        $this->ensureCompanyItem($reminderItem);

        $oldValues = $reminderItem->toArray();

        $reminderItem->update([
            'status' => 'archived',
            'archived_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'reminder_item.archived',
            subject: $reminderItem,
            oldValues: $oldValues,
            newValues: $reminderItem->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Item de vencimento arquivado com sucesso.',
        ]);
    }

    private function ensureCompanyItem(ReminderItem $item): void
    {
        abort_if(
            $item->company_id !== app('company.id'),
            404,
            'Item de vencimento não encontrado.'
        );
    }
}
