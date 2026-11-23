<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aso;
use App\Models\EmploymentLink;
use App\Models\ManagedCompany;
use App\Services\ActivityLogger;
use App\Services\AsoMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsoOperationsController extends Controller
{
    private function tenant(): int
    {
        $id = (int) app('company.id');
        abort_unless($id > 0 && DB::table('company_user')->where('company_id', $id)
            ->where('user_id', auth()->id())->exists(), 403);
        return $id;
    }

    private function company(ManagedCompany $managedCompany): ManagedCompany
    {
        abort_unless($managedCompany->company_id === $this->tenant(), 404);
        return $managedCompany;
    }

    public function report(AsoMonitoringService $monitor)
    {
        return response()->json($monitor->report($this->tenant()));
    }

    public function index(ManagedCompany $managedCompany, EmploymentLink $employmentLink)
    {
        $company = $this->company($managedCompany);
        abort_unless($employmentLink->company_id === $this->tenant()
            && $employmentLink->managed_company_id === $company->id, 404);
        return response()->json(Aso::where('company_id', $this->tenant())
            ->where('employment_link_id', $employmentLink->id)
            ->orderByDesc('performed_at')->paginate(30));
    }

    public function store(Request $request, ManagedCompany $managedCompany, EmploymentLink $employmentLink, ActivityLogger $logger)
    {
        $company = $this->company($managedCompany);
        abort_unless($employmentLink->company_id === $this->tenant()
            && $employmentLink->managed_company_id === $company->id, 404);
        $data = $request->validate([
            'exam_type' => ['required','in:admissional,periodico,retorno,mudanca_risco,demissional'],
            'performed_at' => ['required','date_format:Y-m-d','before_or_equal:today'],
            'next_due_at' => ['nullable','date_format:Y-m-d','after:performed_at'],
            'esocial_submitted' => ['required','boolean'],
            'esocial_submitted_at' => ['nullable','date'],
            'receipt_number' => ['nullable','string','max:255'],
            'notes' => ['nullable','string','max:3000'],
        ]);
        if ($data['exam_type'] === 'demissional' && !empty($data['next_due_at'])) {
            throw ValidationException::withMessages(['next_due_at' => 'ASO demissional não gera vencimento periódico.']);
        }
        if ($data['exam_type'] !== 'demissional' && empty($data['next_due_at'])) {
            throw ValidationException::withMessages(['next_due_at' => 'Informe o próximo vencimento conforme PCMSO.']);
        }
        if (!$data['esocial_submitted'] && (!empty($data['receipt_number']) || !empty($data['esocial_submitted_at']))) {
            throw ValidationException::withMessages(['esocial_submitted' => 'Não informe transmissão ou recibo quando não enviado.']);
        }
        if ($data['esocial_submitted'] && empty($data['esocial_submitted_at'])) {
            $data['esocial_submitted_at'] = now();
        }
        $aso = Aso::create([...$data, 'company_id' => $this->tenant(),
            'managed_company_id' => $company->id, 'employment_link_id' => $employmentLink->id,
            'created_by' => auth()->id()]);
        $logger->log('aso.created', $aso, newValues: $aso->toArray(), companyId: $this->tenant());
        return response()->json(['aso' => $aso, 'message' => 'ASO registrado.'], 201);
    }
}
