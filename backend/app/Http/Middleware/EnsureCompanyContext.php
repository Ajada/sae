<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $companyId = $request->header('X-Company-Id')
            ?: $request->header('__company_id')
            ?: $user->current_company_id;

        if (! $companyId) {
            return response()->json([
                'message' => 'Empresa ativa não informada.',
            ], 422);
        }

        $belongsToCompany = $user->companies()
            ->where('companies.id', $companyId)
            ->exists();

        if (! $belongsToCompany) {
            return response()->json([
                'message' => 'Usuário não possui acesso a esta empresa.',
            ], 403);
        }

        app()->instance('company.id', (int) $companyId);

        return $next($request);
    }
}
