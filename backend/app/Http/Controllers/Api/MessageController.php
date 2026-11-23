<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Jobs\DispatchMessageJob;
use App\Models\Message;
use App\Services\ActivityLogger;
use App\Services\Messages\RecipientResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $messages = Message::query()
            ->where('company_id', $companyId)
            ->withCount('recipients')
            ->with(['provider:id,uuid,name', 'template:id,uuid,name'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('channel'), function ($query) use ($request) {
                $query->where('channel', $request->string('channel'));
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($messages);
    }

    public function store(
        StoreMessageRequest $request,
        RecipientResolverService $recipientResolver,
        ActivityLogger $logger
    ) {
        $companyId = app('company.id');
        $validated = $request->validated();

        $recipients = $recipientResolver->resolve(
            data: $validated,
            companyId: $companyId,
            channel: $validated['channel']
        );

        if ($recipients->isEmpty()) {
            return response()->json([
                'message' => 'Nenhum destinatário válido encontrado para este canal.',
                'warnings' => $recipientResolver->getEmailWarnings($validated, $companyId),
            ], 422);
        }

        $message = DB::transaction(function () use ($validated, $recipients, $companyId) {
            $scheduledAt = ! empty($validated['scheduled_at'])
                ? now()->parse($validated['scheduled_at'])
                : null;

            $status = $scheduledAt && $scheduledAt->isFuture()
                ? 'scheduled'
                : 'pending';

            $message = Message::create([
                'company_id' => $companyId,
                'provider_id' => $validated['provider_id'] ?? null,
                'template_id' => $validated['template_id'] ?? null,
                'channel' => $validated['channel'],
                'source_type' => 'manual',
                'source_id' => null,
                'subject' => $validated['subject'] ?? null,
                'body_html' => $validated['body_html'] ?? null,
                'body_text' => $validated['body_text'] ?? null,
                'variables' => $validated['variables'] ?? [],
                'scheduled_at' => $scheduledAt,
                'status' => $status,
                'allow_fallback' => $validated['allow_fallback'] ?? true,
                'created_by' => auth()->id(),
            ]);

            foreach ($recipients as $recipient) {
                $message->recipients()->create($recipient);
            }

            return $message;
        });

        $logger->log(
            action: 'message.created',
            subject: $message,
            newValues: [
                ...$message->toArray(),
                'recipients_count' => $recipients->count(),
            ]
        );

        if ($message->status === 'pending') {
            DispatchMessageJob::dispatch($message->id)->onQueue('emails');
        }

        return response()->json([
            'message' => $message->status === 'scheduled'
                ? 'Mensagem agendada com sucesso.'
                : 'Mensagem criada e enviada para a fila com sucesso.',
            'data' => $message->load('recipients'),
            'warnings' => $recipientResolver->getEmailWarnings($validated, $companyId),
        ], 201);
    }

    public function show(Message $message)
    {
        $this->ensureCompanyMessage($message);

        return response()->json([
            'message' => $message->load([
                'provider:id,uuid,name,from_email',
                'template:id,uuid,name,key',
                'recipients',
                'logs.provider:id,uuid,name',
            ]),
        ]);
    }

    public function destroy(Message $message, ActivityLogger $logger)
    {
        $this->ensureCompanyMessage($message);

        if (! in_array($message->status, ['pending', 'scheduled'], true)) {
            return response()->json([
                'message' => 'Somente mensagens pendentes ou agendadas podem ser canceladas.',
            ], 422);
        }

        $oldValues = $message->toArray();

        $message->update([
            'status' => 'cancelled',
        ]);

        $logger->log(
            action: 'message.cancelled',
            subject: $message,
            oldValues: $oldValues,
            newValues: $message->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Mensagem cancelada com sucesso.',
        ]);
    }

    private function ensureCompanyMessage(Message $message): void
    {
        abort_if(
            $message->company_id !== app('company.id'),
            404,
            'Mensagem não encontrada.'
        );
    }
}
