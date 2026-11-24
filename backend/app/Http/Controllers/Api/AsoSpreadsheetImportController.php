<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aso;
use App\Models\ManagedCompany;
use App\Models\TeamImportBatch;
use App\Services\ActivityLogger;
use App\Services\AsoSpreadsheetImportService;
use App\Services\TeamSpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AsoSpreadsheetImportController extends Controller
{
    private function scope(ManagedCompany $company, TeamImportBatch $batch): void
    {
        $tenantId = (int) app('company.id');
        abort_unless($tenantId > 0 && $company->company_id === $tenantId &&
            DB::table('company_user')->where('company_id', $tenantId)->where('user_id', auth()->id())->exists(), 404);
        abort_unless($batch->company_id === $tenantId && $batch->managed_company_id === $company->id &&
            $batch->user_id === auth()->id(), 404);
        abort_if(!$batch->mapping || !in_array($batch->status, ['previewed', 'committed'], true), 409, 'Gere a prévia da equipe primeiro.');
        abort_if($batch->expires_at->isPast(), 410, 'O lote expirou. Envie a planilha novamente.');
        abort_unless(Storage::disk('local')->exists($batch->stored_path), 410, 'O arquivo temporário foi removido. Reenvie-o.');
    }

    private function read(TeamImportBatch $batch, TeamSpreadsheetReader $reader): array
    {
        $path = Storage::disk('local')->path($batch->stored_path);
        abort_unless(hash_file('sha256', $path) === $batch->sha256, 409, 'O arquivo foi alterado após o upload.');
        return $reader->read($path);
    }

    public function preview(Request $request, ManagedCompany $managedCompany, TeamImportBatch $teamImportBatch,
        TeamSpreadsheetReader $reader, AsoSpreadsheetImportService $service)
    {
        $this->scope($managedCompany, $teamImportBatch);
        $data = $request->validate(['periodicity_months' => ['required', 'integer', 'between:1,60']]);
        return response()->json($service->preview($this->read($teamImportBatch, $reader), $teamImportBatch,
            $managedCompany, (int) $data['periodicity_months']));
    }

    public function commit(Request $request, ManagedCompany $managedCompany, TeamImportBatch $teamImportBatch,
        TeamSpreadsheetReader $reader, AsoSpreadsheetImportService $service, ActivityLogger $logger)
    {
        $this->scope($managedCompany, $teamImportBatch);
        $data = $request->validate([
            'periodicity_months' => ['required', 'integer', 'between:1,60'],
            'keys' => ['required', 'array', 'max:5000'], 'keys.*' => ['required', 'string', 'size:64'],
            'file_sha256' => ['required', 'string', 'size:64'],
        ]);
        abort_unless(hash_equals($teamImportBatch->sha256, $data['file_sha256']), 409, 'O arquivo confirmado é diferente do enviado.');
        $sheet = $this->read($teamImportBatch, $reader);
        $keys = array_values(array_unique($data['keys']));
        $counts = DB::transaction(function () use ($teamImportBatch, $managedCompany, $service, $sheet, $data, $keys) {
            $locked = TeamImportBatch::whereKey($teamImportBatch->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->expires_at->isPast(), 410, 'Lote expirado.');
            $analysis = $service->preview($sheet, $locked, $managedCompany, (int) $data['periodicity_months']);
            $byKey = collect($analysis['rows'])->keyBy('key');
            foreach ($keys as $key) {
                $row = $byKey->get($key);
                if (!$row || $row['status'] !== 'new') {
                    throw ValidationException::withMessages(['keys' => 'A prévia mudou. Revalide o arquivo antes de confirmar.']);
                }
            }
            $created = 0;
            foreach ($keys as $key) {
                $row = $byKey[$key];
                // Recheck within transaction to avoid duplication of earlier records.
                $exists = Aso::where('company_id', $managedCompany->company_id)
                    ->where('employment_link_id', $row['link_id'])->where('exam_type', $row['exam_type'])
                    ->whereDate('performed_at', $row['performed_at'])->exists();
                if ($exists) throw ValidationException::withMessages(['keys' => 'Exame já existente. Atualize a prévia.']);
                Aso::create([
                    'company_id' => $managedCompany->company_id, 'managed_company_id' => $managedCompany->id,
                    'employment_link_id' => $row['link_id'], 'exam_type' => $row['exam_type'],
                    'performed_at' => $row['performed_at'], 'next_due_at' => $row['next_due_at'],
                    'esocial_submitted' => null,
                    'notes' => 'Importado de planilha: '.$locked->original_name.' (linha '.$row['row'].', coluna '.$row['column'].').',
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }
            return ['created_asos' => $created, 'ignored' => count($analysis['rows']) - $created];
        }, 3);
        $logger->log('aso_import.committed', $teamImportBatch, newValues: $counts, companyId: $managedCompany->company_id);
        return response()->json(['message' => 'ASOs importados com sucesso.', 'result' => $counts]);
    }
}
