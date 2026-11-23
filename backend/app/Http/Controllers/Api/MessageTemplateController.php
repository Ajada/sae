<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageTemplateRequest;
use App\Http\Requests\UpdateMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Services\Messages\MessageTemplateRenderer;
use Illuminate\Http\Request;

class MessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $templates = MessageTemplate::query()
            ->availableForCompany($companyId)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('key', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('channel'), function ($query) use ($request) {
                $query->where('channel', $request->string('channel'));
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->orderByDesc('is_system')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($templates);
    }

    public function store(StoreMessageTemplateRequest $request, ActivityLogger $logger)
    {
        $companyId = app('company.id');
        $validated = $request->validated();

        $template = MessageTemplate::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'key' => $validated['key'] ?? null,
            'channel' => $validated['channel'],
            'subject' => $validated['subject'] ?? null,
            'body_html' => $validated['body_html'] ?? null,
            'body_text' => $validated['body_text'] ?? null,
            'variables' => $validated['variables'] ?? [],
            'is_system' => false,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'message_template.created',
            subject: $template,
            newValues: $template->toArray()
        );

        return response()->json([
            'message' => 'Template cadastrado com sucesso.',
            'template' => $template,
        ], 201);
    }

    public function show(MessageTemplate $messageTemplate)
    {
        $this->ensureCompanyTemplate($messageTemplate);

        return response()->json([
            'template' => $messageTemplate,
        ]);
    }

    public function update(
        UpdateMessageTemplateRequest $request,
        MessageTemplate $messageTemplate,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyTemplate($messageTemplate);

        if ($messageTemplate->is_system) {
            return response()->json([
                'message' => 'Templates do sistema não podem ser alterados.',
            ], 403);
        }

        $oldValues = $messageTemplate->toArray();
        $validated = $request->validated();

        $messageTemplate->update([
            'name' => $validated['name'] ?? $messageTemplate->name,
            'key' => array_key_exists('key', $validated)
                ? $validated['key']
                : $messageTemplate->key,
            'channel' => $validated['channel'] ?? $messageTemplate->channel,
            'subject' => array_key_exists('subject', $validated)
                ? $validated['subject']
                : $messageTemplate->subject,
            'body_html' => array_key_exists('body_html', $validated)
                ? $validated['body_html']
                : $messageTemplate->body_html,
            'body_text' => array_key_exists('body_text', $validated)
                ? $validated['body_text']
                : $messageTemplate->body_text,
            'variables' => array_key_exists('variables', $validated)
                ? $validated['variables']
                : $messageTemplate->variables,
            'is_active' => array_key_exists('is_active', $validated)
                ? $validated['is_active']
                : $messageTemplate->is_active,
            'updated_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'message_template.updated',
            subject: $messageTemplate,
            oldValues: $oldValues,
            newValues: $messageTemplate->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Template atualizado com sucesso.',
            'template' => $messageTemplate->fresh(),
        ]);
    }

    public function destroy(MessageTemplate $messageTemplate, ActivityLogger $logger)
    {
        $this->ensureCompanyTemplate($messageTemplate);

        if ($messageTemplate->is_system) {
            return response()->json([
                'message' => 'Templates do sistema não podem ser removidos.',
            ], 403);
        }

        $oldValues = $messageTemplate->toArray();

        $messageTemplate->delete();

        $logger->log(
            action: 'message_template.deleted',
            subject: $messageTemplate,
            oldValues: $oldValues
        );

        return response()->json([
            'message' => 'Template removido com sucesso.',
        ]);
    }

    public function preview(
        Request $request,
        MessageTemplate $messageTemplate,
        MessageTemplateRenderer $renderer
    ) {
        $this->ensureCompanyTemplate($messageTemplate);

        $validated = $request->validate([
            'variables' => ['nullable', 'array'],
        ]);

        return response()->json([
            'preview' => $renderer->renderTemplate(
                template: $messageTemplate,
                variables: $validated['variables'] ?? []
            ),
        ]);
    }

    private function ensureCompanyTemplate(MessageTemplate $template): void
    {
        $companyId = app('company.id');

        $allowed = $template->is_system && $template->company_id === null
            || $template->company_id === $companyId;

        abort_if(! $allowed, 404, 'Template não encontrado.');
    }
}
