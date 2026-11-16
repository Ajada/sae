<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIntegrationMessageRequest;
use App\Jobs\DispatchMessageJob;
use App\Models\Message;
use App\Services\ActivityLogger;
use App\Services\Messages\RecipientResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IntegrationMessageController extends Controller
{
    public function store(
        StoreIntegrationMessageRequest $request,
        RecipientResolverService $recipientResolver,
        ActivityLogger $logger
    ) {
        $companyId = app('company.id');
        $apiClient = app('api.client');

        $validated = $request->validated();

        if (! empty($validated['idempotency_key'])) {
            $existingMessage = Message::query()
                ->where('company_id', $companyId)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if ($existingMessage) {
                return response()->json([
                    'message' => 'Mensagem já havia sido criada para esta chave de idempotência.',
                    'data' => $existingMessage->load('recipients'),
                ], 200);
            }
        }

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

        $message = DB::transaction(function () use (
            $validated,
            $recipients,
            $companyId,
            $apiClient
        ) {
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

                'source_type' => 'api',
                'source_id' => $apiClient->id,
                'external_id' => $validated['external_id'] ?? null,
                'idempotency_key' => $validated['idempotency_key'] ?? null,

                'subject' => $validated['subject'] ?? null,
                'body_html' => $validated['body_html'] ?? null,
                'body_text' => $validated['body_text'] ?? null,

                'variables' => $validated['variables'] ?? [],
                'metadata' => $validated['metadata'] ?? [],

                'scheduled_at' => $scheduledAt,
                'status' => $status,
                'allow_fallback' => $validated['allow_fallback'] ?? true,
                'created_by' => null,
            ]);

            foreach ($recipients as $recipient) {
                $message->recipients()->create($recipient);
            }

            return $message;
        });

        $logger->log(
            action: 'integration.message.created',
            subject: $message,
            newValues: [
                ...$message->toArray(),
                'api_client_id' => $apiClient->id,
                'api_client_name' => $apiClient->name,
                'recipients_count' => $recipients->count(),
            ],
            companyId: $companyId,
            userId: null
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

    public function show(Request $request, Message $message)
    {
        $companyId = app('company.id');
        $apiClient = app('api.client');

        abort_if(
            $message->company_id !== $companyId,
            404,
            'Mensagem não encontrada.'
        );

        abort_if(
            $message->source_type === 'api'
            && $message->source_id !== $apiClient->id,
            403,
            'Esta API key não possui acesso a esta mensagem.'
        );

        return response()->json([
            'message' => $message->load([
                'recipients',
                'logs.provider:id,uuid,name',
                'provider:id,uuid,name',
                'template:id,uuid,name,key',
            ]),
        ]);
    }

    public function statusByIdempotencyKey(Request $request)
    {
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string'],
        ]);

        $companyId = app('company.id');
        $apiClient = app('api.client');

        $message = Message::query()
            ->where('company_id', $companyId)
            ->where('source_type', 'api')
            ->where('source_id', $apiClient->id)
            ->where('idempotency_key', $validated['idempotency_key'])
            ->firstOrFail();

        return response()->json([
            'message' => $message->load([
                'recipients',
                'logs.provider:id,uuid,name',
            ]),
        ]);
    }
}
