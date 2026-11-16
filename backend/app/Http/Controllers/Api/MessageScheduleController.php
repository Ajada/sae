<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageScheduleRequest;
use App\Http\Requests\UpdateMessageScheduleRequest;
use App\Models\MessageSchedule;
use App\Services\ActivityLogger;
use App\Services\Recurring\RecurringScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageScheduleController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $schedules = MessageSchedule::query()
            ->where('company_id', $companyId)
            ->withCount('recipients')
            ->with(['provider:id,uuid,name', 'template:id,uuid,name,key'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('frequency'), function ($query) use ($request) {
                $query->where('frequency', $request->string('frequency'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($schedules);
    }

    public function store(
        StoreMessageScheduleRequest $request,
        RecurringScheduleService $scheduleService,
        ActivityLogger $logger
    ) {
        $companyId = app('company.id');
        $validated = $request->validated();

        $schedule = DB::transaction(function () use ($validated, $companyId, $scheduleService) {
            $schedule = MessageSchedule::create([
                'company_id' => $companyId,
                'provider_id' => $validated['provider_id'] ?? null,
                'template_id' => $validated['template_id'] ?? null,
                'name' => $validated['name'],
                'channel' => $validated['channel'],
                'subject' => $validated['subject'] ?? null,
                'body_html' => $validated['body_html'] ?? null,
                'body_text' => $validated['body_text'] ?? null,
                'variables' => $validated['variables'] ?? [],
                'metadata' => $validated['metadata'] ?? [],
                'frequency' => $validated['frequency'],
                'day_of_week' => $validated['day_of_week'] ?? null,
                'day_of_month' => $validated['day_of_month'] ?? null,
                'month' => $validated['month'] ?? null,
                'send_time' => $validated['send_time'],
                'timezone' => $validated['timezone'] ?? 'America/Sao_Paulo',
                'starts_at' => $validated['starts_at'] ?? null,
                'ends_at' => $validated['ends_at'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'allow_fallback' => $validated['allow_fallback'] ?? true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($validated['recipients'] as $recipient) {
                $schedule->recipients()->create([
                    'company_id' => $companyId,
                    'type' => $recipient['type'],
                    'contact_id' => $recipient['contact_id'] ?? null,
                    'contact_list_id' => $recipient['contact_list_id'] ?? null,
                    'name' => $recipient['name'] ?? null,
                    'email' => $recipient['email'] ?? null,
                    'phone' => $recipient['phone'] ?? null,
                ]);
            }

            $schedule->update([
                'next_run_at' => $scheduleService->calculateNextRun($schedule),
            ]);

            return $schedule;
        });

        $logger->log(
            action: 'message_schedule.created',
            subject: $schedule,
            newValues: $schedule->load('recipients')->toArray()
        );

        return response()->json([
            'message' => 'Disparo recorrente cadastrado com sucesso.',
            'schedule' => $schedule->load(['recipients', 'provider:id,uuid,name', 'template:id,uuid,name,key']),
        ], 201);
    }

    public function show(MessageSchedule $messageSchedule)
    {
        $this->ensureCompanySchedule($messageSchedule);

        return response()->json([
            'schedule' => $messageSchedule->load([
                'recipients.contact:id,uuid,name,email,phone',
                'recipients.contactList:id,uuid,name',
                'provider:id,uuid,name,from_email',
                'template:id,uuid,name,key',
                'runs.message:id,uuid,status,subject,sent_at,failed_at',
            ]),
        ]);
    }

    public function update(
        UpdateMessageScheduleRequest $request,
        MessageSchedule $messageSchedule,
        RecurringScheduleService $scheduleService,
        ActivityLogger $logger
    ) {
        $this->ensureCompanySchedule($messageSchedule);

        $oldValues = $messageSchedule->load('recipients')->toArray();
        $validated = $request->validated();

        DB::transaction(function () use ($messageSchedule, $validated, $scheduleService) {
            $messageSchedule->update([
                'provider_id' => array_key_exists('provider_id', $validated)
                    ? $validated['provider_id']
                    : $messageSchedule->provider_id,
                'template_id' => array_key_exists('template_id', $validated)
                    ? $validated['template_id']
                    : $messageSchedule->template_id,
                'name' => $validated['name'] ?? $messageSchedule->name,
                'channel' => $validated['channel'] ?? $messageSchedule->channel,
                'subject' => array_key_exists('subject', $validated)
                    ? $validated['subject']
                    : $messageSchedule->subject,
                'body_html' => array_key_exists('body_html', $validated)
                    ? $validated['body_html']
                    : $messageSchedule->body_html,
                'body_text' => array_key_exists('body_text', $validated)
                    ? $validated['body_text']
                    : $messageSchedule->body_text,
                'variables' => array_key_exists('variables', $validated)
                    ? $validated['variables']
                    : $messageSchedule->variables,
                'metadata' => array_key_exists('metadata', $validated)
                    ? $validated['metadata']
                    : $messageSchedule->metadata,
                'frequency' => $validated['frequency'] ?? $messageSchedule->frequency,
                'day_of_week' => array_key_exists('day_of_week', $validated)
                    ? $validated['day_of_week']
                    : $messageSchedule->day_of_week,
                'day_of_month' => array_key_exists('day_of_month', $validated)
                    ? $validated['day_of_month']
                    : $messageSchedule->day_of_month,
                'month' => array_key_exists('month', $validated)
                    ? $validated['month']
                    : $messageSchedule->month,
                'send_time' => $validated['send_time'] ?? $messageSchedule->send_time,
                'timezone' => $validated['timezone'] ?? $messageSchedule->timezone,
                'starts_at' => array_key_exists('starts_at', $validated)
                    ? $validated['starts_at']
                    : $messageSchedule->starts_at,
                'ends_at' => array_key_exists('ends_at', $validated)
                    ? $validated['ends_at']
                    : $messageSchedule->ends_at,
                'status' => $validated['status'] ?? $messageSchedule->status,
                'allow_fallback' => array_key_exists('allow_fallback', $validated)
                    ? $validated['allow_fallback']
                    : $messageSchedule->allow_fallback,
                'updated_by' => auth()->id(),
            ]);

            if (array_key_exists('recipients', $validated)) {
                $messageSchedule->recipients()->delete();

                foreach ($validated['recipients'] as $recipient) {
                    $messageSchedule->recipients()->create([
                        'company_id' => $messageSchedule->company_id,
                        'type' => $recipient['type'],
                        'contact_id' => $recipient['contact_id'] ?? null,
                        'contact_list_id' => $recipient['contact_list_id'] ?? null,
                        'name' => $recipient['name'] ?? null,
                        'email' => $recipient['email'] ?? null,
                        'phone' => $recipient['phone'] ?? null,
                    ]);
                }
            }

            $messageSchedule->update([
                'next_run_at' => $scheduleService->calculateNextRun($messageSchedule->fresh()),
            ]);
        });

        $logger->log(
            action: 'message_schedule.updated',
            subject: $messageSchedule,
            oldValues: $oldValues,
            newValues: $messageSchedule->fresh()->load('recipients')->toArray()
        );

        return response()->json([
            'message' => 'Disparo recorrente atualizado com sucesso.',
            'schedule' => $messageSchedule->fresh()->load('recipients'),
        ]);
    }

    public function destroy(MessageSchedule $messageSchedule, ActivityLogger $logger)
    {
        $this->ensureCompanySchedule($messageSchedule);

        $oldValues = $messageSchedule->toArray();

        $messageSchedule->update([
            'status' => 'cancelled',
            'next_run_at' => null,
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'message_schedule.cancelled',
            subject: $messageSchedule,
            oldValues: $oldValues,
            newValues: $messageSchedule->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Disparo recorrente cancelado com sucesso.',
        ]);
    }

    private function ensureCompanySchedule(MessageSchedule $schedule): void
    {
        abort_if(
            $schedule->company_id !== app('company.id'),
            404,
            'Disparo recorrente não encontrado.'
        );
    }
}
