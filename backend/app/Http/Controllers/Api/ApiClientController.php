<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiClientRequest;
use App\Http\Requests\UpdateApiClientRequest;
use App\Models\ApiClient;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiClientController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $clients = ApiClient::query()
            ->where('company_id', $companyId)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($clients);
    }

    public function store(StoreApiClientRequest $request, ActivityLogger $logger)
    {
        $companyId = app('company.id');
        $validated = $request->validated();

        $plainToken = 'xflow_' . Str::random(80);

        $client = ApiClient::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'token_hash' => hash('sha256', $plainToken),
            'allowed_ips' => $validated['allowed_ips'] ?? null,
            'allowed_origins' => $validated['allowed_origins'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);

        $logger->log(
            action: 'api_client.created',
            subject: $client,
            newValues: $client->toArray()
        );

        return response()->json([
            'message' => 'API client criado com sucesso. Guarde a chave, ela será exibida apenas uma vez.',
            'api_client' => $client,
            'api_key' => $plainToken,
        ], 201);
    }

    public function show(ApiClient $apiClient)
    {
        $this->ensureCompanyClient($apiClient);

        return response()->json([
            'api_client' => $apiClient,
        ]);
    }

    public function update(
        UpdateApiClientRequest $request,
        ApiClient $apiClient,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyClient($apiClient);

        $oldValues = $apiClient->toArray();
        $validated = $request->validated();

        $apiClient->update([
            'name' => $validated['name'] ?? $apiClient->name,
            'allowed_ips' => array_key_exists('allowed_ips', $validated)
                ? $validated['allowed_ips']
                : $apiClient->allowed_ips,
            'allowed_origins' => array_key_exists('allowed_origins', $validated)
                ? $validated['allowed_origins']
                : $apiClient->allowed_origins,
            'is_active' => array_key_exists('is_active', $validated)
                ? $validated['is_active']
                : $apiClient->is_active,
        ]);

        $logger->log(
            action: 'api_client.updated',
            subject: $apiClient,
            oldValues: $oldValues,
            newValues: $apiClient->fresh()->toArray()
        );

        return response()->json([
            'message' => 'API client atualizado com sucesso.',
            'api_client' => $apiClient->fresh(),
        ]);
    }

    public function destroy(ApiClient $apiClient, ActivityLogger $logger)
    {
        $this->ensureCompanyClient($apiClient);

        $oldValues = $apiClient->toArray();

        $apiClient->update([
            'is_active' => false,
        ]);

        $logger->log(
            action: 'api_client.revoked',
            subject: $apiClient,
            oldValues: $oldValues,
            newValues: $apiClient->fresh()->toArray()
        );

        return response()->json([
            'message' => 'API client revogado com sucesso.',
        ]);
    }

    public function regenerate(ApiClient $apiClient, ActivityLogger $logger)
    {
        $this->ensureCompanyClient($apiClient);

        $oldValues = $apiClient->toArray();

        $plainToken = 'xflow_' . Str::random(80);

        $apiClient->update([
            'token_hash' => hash('sha256', $plainToken),
            'is_active' => true,
        ]);

        $logger->log(
            action: 'api_client.regenerated',
            subject: $apiClient,
            oldValues: $oldValues,
            newValues: $apiClient->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Nova API key gerada com sucesso. Guarde a chave, ela será exibida apenas uma vez.',
            'api_client' => $apiClient->fresh(),
            'api_key' => $plainToken,
        ]);
    }

    private function ensureCompanyClient(ApiClient $client): void
    {
        abort_if(
            $client->company_id !== app('company.id'),
            404,
            'API client não encontrado.'
        );
    }
}
