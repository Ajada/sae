<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryProviderRequest;
use App\Http\Requests\UpdateDeliveryProviderRequest;
use App\Models\DeliveryProvider;
use App\Services\ActivityLogger;
use App\Services\Providers\SmtpTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class DeliveryProviderController extends Controller
{
    public function index(Request $request)
    {
        $companyId = app('company.id');

        $providers = DeliveryProvider::query()
            ->where('company_id', $companyId)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('host', 'like', "%{$search}%")
                        ->orWhere('from_email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('channel'), function ($query) use ($request) {
                $query->where('channel', $request->string('channel'));
            })
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json($providers);
    }

    public function store(StoreDeliveryProviderRequest $request, ActivityLogger $logger)
    {
        $companyId = app('company.id');
        $validated = $request->validated();

        $provider = DB::transaction(function () use ($validated, $companyId) {
            $isDefault = (bool) ($validated['is_default'] ?? false);

            if ($isDefault) {
                DeliveryProvider::query()
                    ->where('company_id', $companyId)
                    ->where('channel', $validated['channel'])
                    ->update(['is_default' => false]);
            }

            return DeliveryProvider::create([
                'company_id' => $companyId,
                'name' => $validated['name'],

                'channel' => $validated['channel'],
                'driver' => $validated['driver'],

                'host' => $validated['host'],
                'port' => $validated['port'],
                'username' => $validated['username'] ?? null,
                'password_encrypted' => ! empty($validated['password'])
                    ? Crypt::encryptString($validated['password'])
                    : null,
                'encryption' => $validated['encryption'] ?? null,

                'from_name' => $validated['from_name'],
                'from_email' => $validated['from_email'],
                'reply_to_email' => $validated['reply_to_email'] ?? null,

                'is_default' => $isDefault,
                'is_active' => $validated['is_active'] ?? true,
                'priority' => $validated['priority'] ?? 1,

                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
        });

        $logger->log(
            action: 'delivery_provider.created',
            subject: $provider,
            newValues: $provider->toArray()
        );

        return response()->json([
            'message' => 'Provedor cadastrado com sucesso.',
            'provider' => $provider,
        ], 201);
    }

    public function show(DeliveryProvider $deliveryProvider)
    {
        $this->ensureCompanyProvider($deliveryProvider);

        return response()->json([
            'provider' => $deliveryProvider,
        ]);
    }

    public function update(
        UpdateDeliveryProviderRequest $request,
        DeliveryProvider $deliveryProvider,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyProvider($deliveryProvider);

        $companyId = app('company.id');
        $validated = $request->validated();
        $oldValues = $deliveryProvider->toArray();

        DB::transaction(function () use ($validated, $deliveryProvider, $companyId) {
            $channel = $validated['channel'] ?? $deliveryProvider->channel;
            $isDefault = array_key_exists('is_default', $validated)
                ? (bool) $validated['is_default']
                : $deliveryProvider->is_default;

            if ($isDefault) {
                DeliveryProvider::query()
                    ->where('company_id', $companyId)
                    ->where('channel', $channel)
                    ->where('id', '!=', $deliveryProvider->id)
                    ->update(['is_default' => false]);
            }

            $data = [
                'name' => $validated['name'] ?? $deliveryProvider->name,

                'channel' => $validated['channel'] ?? $deliveryProvider->channel,
                'driver' => $validated['driver'] ?? $deliveryProvider->driver,

                'host' => $validated['host'] ?? $deliveryProvider->host,
                'port' => $validated['port'] ?? $deliveryProvider->port,
                'username' => array_key_exists('username', $validated)
                    ? $validated['username']
                    : $deliveryProvider->username,

                'encryption' => array_key_exists('encryption', $validated)
                    ? $validated['encryption']
                    : $deliveryProvider->encryption,

                'from_name' => $validated['from_name'] ?? $deliveryProvider->from_name,
                'from_email' => $validated['from_email'] ?? $deliveryProvider->from_email,

                'reply_to_email' => array_key_exists('reply_to_email', $validated)
                    ? $validated['reply_to_email']
                    : $deliveryProvider->reply_to_email,

                'is_default' => $isDefault,
                'is_active' => array_key_exists('is_active', $validated)
                    ? $validated['is_active']
                    : $deliveryProvider->is_active,

                'priority' => $validated['priority'] ?? $deliveryProvider->priority,

                'updated_by' => auth()->id(),
            ];

            if (! empty($validated['password'])) {
                $data['password_encrypted'] = Crypt::encryptString($validated['password']);
            }

            $deliveryProvider->update($data);
        });

        $logger->log(
            action: 'delivery_provider.updated',
            subject: $deliveryProvider,
            oldValues: $oldValues,
            newValues: $deliveryProvider->fresh()->toArray()
        );

        return response()->json([
            'message' => 'Provedor atualizado com sucesso.',
            'provider' => $deliveryProvider->fresh(),
        ]);
    }

    public function destroy(DeliveryProvider $deliveryProvider, ActivityLogger $logger)
    {
        $this->ensureCompanyProvider($deliveryProvider);

        $oldValues = $deliveryProvider->toArray();

        $deliveryProvider->delete();

        $logger->log(
            action: 'delivery_provider.deleted',
            subject: $deliveryProvider,
            oldValues: $oldValues
        );

        return response()->json([
            'message' => 'Provedor removido com sucesso.',
        ]);
    }

    public function test(
        Request $request,
        DeliveryProvider $deliveryProvider,
        SmtpTestService $smtpTestService,
        ActivityLogger $logger
    ) {
        $this->ensureCompanyProvider($deliveryProvider);

        $validated = $request->validate([
            'to' => ['nullable', 'email', 'max:255'],
        ]);

        $result = $smtpTestService->test(
            provider: $deliveryProvider,
            to: $validated['to'] ?? null
        );

        $logger->log(
            action: $result['success']
                ? 'delivery_provider.test_success'
                : 'delivery_provider.test_failed',
            subject: $deliveryProvider,
            newValues: [
                'to' => $validated['to'] ?? $deliveryProvider->from_email,
                'result' => $result,
            ]
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    private function ensureCompanyProvider(DeliveryProvider $provider): void
    {
        abort_if(
            $provider->company_id !== app('company.id'),
            404,
            'Provedor não encontrado.'
        );
    }
}
