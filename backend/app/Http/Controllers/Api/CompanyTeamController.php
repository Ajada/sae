<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmploymentLink;
use App\Models\ManagedCompany;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyTeamController extends Controller
{
    private function tenantId(): int
    {
        $id = (int) app('company.id');
        abort_unless($id > 0 && DB::table('company_user')->where('company_id', $id)
            ->where('user_id', auth()->id())->exists(), 403);
        return $id;
    }

    private function company(ManagedCompany $company): ManagedCompany
    {
        abort_unless($company->company_id === $this->tenantId(), 404);
        return $company;
    }

    private function employee(ManagedCompany $company, Employee $employee): Employee
    {
        abort_unless($employee->company_id === $this->tenantId()
            && $employee->links()->where('managed_company_id', $company->id)->exists(), 404);
        return $employee;
    }

    private function link(ManagedCompany $company, Employee $employee, EmploymentLink $link): EmploymentLink
    {
        abort_unless($link->company_id === $this->tenantId()
            && $link->managed_company_id === $company->id
            && $link->employee_id === $employee->id, 404);
        return $link;
    }

    private function checkCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($length = 9; $length < 11; $length++) {
            $sum = 0;
            for ($i = 0; $i < $length; $i++) $sum += ((int) $cpf[$i]) * ($length + 1 - $i);
            $digit = ($sum * 10) % 11;
            if ($digit === 10) $digit = 0;
            if ($digit !== (int) $cpf[$length]) return false;
        }
        return true;
    }

    private function linkRules(): array
    {
        return [
            'registration' => ['nullable', 'string', 'max:80'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'admitted_at' => ['required', 'date_format:Y-m-d'],
            'terminated_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:admitted_at'],
        ];
    }

    public function index(Request $request, ManagedCompany $managedCompany)
    {
        $company = $this->company($managedCompany);
        $search = trim((string) $request->query('search', ''));
        $onlyMultiple = $request->boolean('multiple_links');
        $showHistoryOnly = $request->boolean('history_only');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $items = Employee::query()->where('company_id', $this->tenantId())
            ->whereHas('links', fn ($q) => $q->where('managed_company_id', $company->id))
            ->with(['links' => fn ($q) => $q->where('managed_company_id', $company->id)->orderByDesc('admitted_at')->orderByDesc('id')])
            ->when($search !== '', function ($q) use ($search, $company) {
                $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', '%' . preg_replace('/\D/', '', $search) . '%')
                    ->orWhereHas('links', fn ($links) => $links->where('managed_company_id', $company->id)
                        ->where('registration', 'like', "%{$search}%")));
            })
            ->when($onlyMultiple, fn ($q) => $q->whereHas('links', fn ($links) => $links->where('managed_company_id', $company->id), '>=', 2))
            ->when($showHistoryOnly, fn ($q) => $q->whereDoesntHave('links', fn ($links) => $links->where('managed_company_id', $company->id)->whereNull('terminated_at')))
            ->orderBy('name')->paginate($perPage);
        return response()->json($items);
    }

    public function store(Request $request, ManagedCompany $managedCompany, ActivityLogger $logger)
    {
        $company = $this->company($managedCompany);
        // O CPF é identificado globalmente dentro do assinante, não apenas da empresa cliente.
        $request->merge(['cpf' => preg_replace('/\D/', '', (string) $request->input('cpf'))]);
        $data = Validator::make($request->all(), array_merge([
            'name' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'digits:11', function ($attribute, $value, $fail) {
                if (!$this->checkCpf((string) $value)) $fail('CPF inválido.');
            }],
        ], $this->linkRules()))->validate();

        $result = DB::transaction(function () use ($data, $company) {
            $tenantId = $this->tenantId();
            $employee = Employee::where('company_id', $tenantId)->where('cpf', $data['cpf'])->first();
            if (!$employee) $employee = Employee::create(['company_id' => $tenantId, 'cpf' => $data['cpf'], 'name' => $data['name']]);
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            // Não sobrescrever dados de uma pessoa já cadastrada em outra empresa.
            $this->assertCanCreateLink($company, $employee, $data);
            $link = $employee->links()->create($this->linkPayload($data, $company, $tenantId));
            return [$employee, $link];
        }, 3);
        [$employee, $link] = $result;
        $logger->log('employment_link.created', $link, newValues: $link->toArray(), companyId: $this->tenantId());
        return response()->json(['message' => 'Colaborador vinculado à empresa.', 'employee' => $employee->load(['links' => fn ($q) => $q->where('managed_company_id', $company->id)]), 'employment_link' => $link], 201);
    }

    public function show(ManagedCompany $managedCompany, Employee $employee)
    {
        $company = $this->company($managedCompany);
        $this->employee($company, $employee);
        return response()->json(['employee' => $employee->load(['links' => fn ($q) => $q->where('managed_company_id', $company->id)->orderByDesc('admitted_at')])]);
    }

    public function addLink(Request $request, ManagedCompany $managedCompany, Employee $employee, ActivityLogger $logger)
    {
        $company = $this->company($managedCompany);
        $this->employee($company, $employee);
        $data = $request->validate($this->linkRules());
        $link = DB::transaction(function () use ($company, $employee, $data) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $this->assertCanCreateLink($company, $employee, $data);
            return $employee->links()->create($this->linkPayload($data, $company, $this->tenantId()));
        }, 3);
        $logger->log('employment_link.created', $link, newValues: $link->toArray(), companyId: $this->tenantId());
        return response()->json(['message' => 'Vínculo histórico cadastrado.', 'employment_link' => $link], 201);
    }

    public function updateLink(Request $request, ManagedCompany $managedCompany, Employee $employee, EmploymentLink $employmentLink, ActivityLogger $logger)
    {
        $company = $this->company($managedCompany);
        $this->employee($company, $employee);
        $this->link($company, $employee, $employmentLink);
        $data = $request->validate([
            'registration' => ['sometimes', 'nullable', 'string', 'max:80'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'terminated_at' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:' . $employmentLink->admitted_at->format('Y-m-d')],
            'is_away' => ['sometimes', 'boolean'],
            'away_since' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $old = $employmentLink->toArray();
        $updated = DB::transaction(function () use ($employee, $company, $employmentLink, $data) {
            Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            $link = EmploymentLink::whereKey($employmentLink->id)->lockForUpdate()->firstOrFail();
            $nextTerminated = array_key_exists('terminated_at', $data) ? $data['terminated_at'] : ($link->terminated_at?->format('Y-m-d'));
            if ($nextTerminated === null) {
                $otherCurrent = EmploymentLink::where('managed_company_id', $company->id)
                    ->where('employee_id', $employee->id)->whereNull('terminated_at')->whereKeyNot($link->id)->exists();
                if ($otherCurrent) throw ValidationException::withMessages(['terminated_at' => 'Já existe outro vínculo vigente nesta empresa.']);
            }
            $nextAway = array_key_exists('is_away', $data) ? $data['is_away'] : $link->is_away;
            if ($nextTerminated !== null && $nextAway) throw ValidationException::withMessages(['is_away' => 'Um vínculo desligado não pode permanecer afastado.']);
            if (!$nextAway) $data['away_since'] = null;
            if ($nextAway && !($data['away_since'] ?? $link->away_since)) $data['away_since'] = now()->toDateString();
            $link->update($data);
            return $link->fresh();
        }, 3);
        $logger->log('employment_link.updated', $updated, oldValues: $old, newValues: $updated->toArray(), companyId: $this->tenantId());
        return response()->json(['message' => 'Vínculo atualizado.', 'employment_link' => $updated]);
    }

    // Endpoints planos para reutilizar createCrudStore do template X Flow.
    public function indexFlat(Request $request)
    {
        $company = ManagedCompany::where('uuid', $request->query('managed_company_uuid'))->firstOrFail();
        return $this->index($request, $company);
    }

    public function storeFlat(Request $request, ActivityLogger $logger)
    {
        $company = ManagedCompany::where('uuid', $request->input('managed_company_uuid'))->firstOrFail();
        return $this->store($request, $company, $logger);
    }

    public function addLinkFlat(Request $request, Employee $employee, ActivityLogger $logger)
    {
        $company = ManagedCompany::where('uuid', $request->input('managed_company_uuid'))->firstOrFail();
        return $this->addLink($request, $company, $employee, $logger);
    }

    public function updateLinkFlat(Request $request, EmploymentLink $employmentLink, ActivityLogger $logger)
    {
        $company = ManagedCompany::findOrFail($employmentLink->managed_company_id);
        $employee = Employee::findOrFail($employmentLink->employee_id);
        return $this->updateLink($request, $company, $employee, $employmentLink, $logger);
    }

    private function linkPayload(array $data, ManagedCompany $company, int $tenantId): array
    {
        return [
            'company_id' => $tenantId,
            'managed_company_id' => $company->id,
            'registration' => $data['registration'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'department' => $data['department'] ?? null,
            'admitted_at' => $data['admitted_at'],
            'terminated_at' => $data['terminated_at'] ?? null,
        ];
    }

    private function assertCanCreateLink(ManagedCompany $company, Employee $employee, array $data): void
    {
        if (empty($data['terminated_at']) && $employee->links()->where('managed_company_id', $company->id)->whereNull('terminated_at')->exists()) {
            throw ValidationException::withMessages(['admitted_at' => 'Este colaborador já possui um vínculo vigente nesta empresa.']);
        }
        if (!empty($data['registration']) && EmploymentLink::where('managed_company_id', $company->id)
                ->where('registration', $data['registration'])->exists()) {
            throw ValidationException::withMessages(['registration' => 'Esta matrícula já está cadastrada nesta empresa.']);
        }
    }
}
