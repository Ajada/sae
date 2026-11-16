<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->header('X-Flow-Api-Key');

        if (! $plainToken) {
            return response()->json([
                'message' => 'API key não informada.',
            ], 401);
        }

        $client = ApiClient::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();

        if (! $client) {
            return response()->json([
                'message' => 'API key inválida.',
            ], 401);
        }

        if (! $this->ipAllowed($client, $request)) {
            return response()->json([
                'message' => 'IP não autorizado para esta API key.',
            ], 403);
        }

        if (! $this->originAllowed($client, $request)) {
            return response()->json([
                'message' => 'Origem não autorizada para esta API key.',
            ], 403);
        }

        $client->forceFill([
            'last_used_at' => now(),
        ])->save();

        app()->instance('api.client', $client);
        app()->instance('company.id', $client->company_id);

        return $next($request);
    }

    private function ipAllowed(ApiClient $client, Request $request): bool
    {
        $allowedIps = $client->allowed_ips ?? [];

        if (empty($allowedIps)) {
            return true;
        }

        return in_array($request->ip(), $allowedIps, true);
    }

    private function originAllowed(ApiClient $client, Request $request): bool
    {
        $allowedOrigins = $client->allowed_origins ?? [];

        if (empty($allowedOrigins)) {
            return true;
        }

        $origin = $request->header('Origin')
            ?: $request->header('Referer');

        if (! $origin) {
            return true;
        }

        foreach ($allowedOrigins as $allowedOrigin) {
            if (str_starts_with($origin, $allowedOrigin)) {
                return true;
            }
        }

        return false;
    }
}
