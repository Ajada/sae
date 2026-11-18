<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagedCompanyRequest;
use App\Http\Requests\UpdateManagedCompanyRequest;
use App\Models\ManagedCompany;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagedCompanyController extends Controller
{
    private function accountId(): int
    {
        $id = (int) app('company.id');
        abort_unless(
            $id > 0 && DB::table('company_user')->where('company_id', $id)
                ->where('user_id', auth()->id())->exists(),
            403, 'Acesso não autorizado à conta.'
        );
        return $id;
    }

    private function scoped(ManagedCompany $managedCompany): ManagedCompany
    {
        abort_unless($managedCompany->company_id === $this->accountId(), 404);
        return $managedCompany;
    }

    public function index(Request $request)
    {
        $accountId = $this->accountId();
        $search = trim((string) $request->query('search', ''));
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $companies = ManagedCompany::query()
            ->where('company_id', $accountId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('corporate_name', 'like', "%{$search}%")
                      ->orWhere('trade_name', 'like', "%{$search}%")
                      ->when(preg_replace('/\D/', '', $search) !== '',
                          fn ($sub) => $sub->orWhere('cnpj', 'like', '%' . preg_replace('/\D/', '', $search) . '%'));
                });
            })
            ->when($request->has('is_active') && $request->query('is_active') !== '',
                fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('corporate_name')
            ->paginate($perPage);

        return response()->json($companies);
    }

    public function store(StoreManagedCompanyRequest $request, ActivityLogger $logger)
    {
        $accountId = $this->accountId();
        $company = ManagedCompany::create([
            ...$request->validated(),
            'company_id' => $accountId,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        $logger->log('managed_company.created', $company, newValues: $company->toArray(), companyId: $accountId);

        return response()->json(['message' => 'Empresa cadastrada.', 'managed_company' => $company], 201);
    }

    public function show(ManagedCompany $managedCompany)
    {
        return response()->json(['managed_company' => $this->scoped($managedCompany)]);
    }

    public function update(UpdateManagedCompanyRequest $request, ManagedCompany $managedCompany, ActivityLogger $logger)
    {
        $company = $this->scoped($managedCompany);
        $old = $company->toArray();
        $company->update([...$request->validated(), 'updated_by' => auth()->id()]);
        $logger->log('managed_company.updated', $company, oldValues: $old,
            newValues: $company->fresh()->toArray(), companyId: $this->accountId());

        return response()->json(['message' => 'Empresa atualizada.', 'managed_company' => $company->fresh()]);
    }

    public function destroy(ManagedCompany $managedCompany, ActivityLogger $logger)
    {
        $company = $this->scoped($managedCompany);
        $old = $company->toArray();
        $company->delete();
        $logger->log('managed_company.deleted', $company, oldValues: $old, companyId: $this->accountId());

        return response()->json(['message' => 'Empresa arquivada.']);
    }
}
