<?php

namespace App\Services\Messages;

use App\Models\DeliveryProvider;
use App\Models\Message;
use RuntimeException;

class ProviderResolverService
{
    public function resolve(Message $message): DeliveryProvider
    {
        if ($message->provider_id) {
            $provider = DeliveryProvider::query()
                ->where('company_id', $message->company_id)
                ->where('id', $message->provider_id)
                ->where('is_active', true)
                ->first();

            if ($provider) {
                return $provider;
            }
        }

        $provider = DeliveryProvider::query()
            ->where('company_id', $message->company_id)
            ->where('channel', $message->channel)
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('priority')
            ->first();

        if (! $provider) {
            throw new RuntimeException('Nenhum provedor ativo encontrado para este canal.');
        }

        return $provider;
    }

    public function fallbackProviders(Message $message, ?int $exceptProviderId = null)
    {
        return DeliveryProvider::query()
            ->where('company_id', $message->company_id)
            ->where('channel', $message->channel)
            ->where('is_active', true)
            ->when($exceptProviderId, function ($query) use ($exceptProviderId) {
                $query->where('id', '!=', $exceptProviderId);
            })
            ->orderBy('priority')
            ->get();
    }
}