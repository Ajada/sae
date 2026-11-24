<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmploymentLink;
use App\Models\ManagedCompany;
use App\Models\TeamImportBatch;
use App\Services\ActivityLogger;
use App\Services\TeamImportAnalyzer;
use App\Services\TeamSpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamImportController extends Controller
{
    private function scopedCompany(ManagedCompany $managedCompany): ManagedCompany
    {
        $id = (int) app('company.id');
        abort_unless($id > 0 && $managedCompany->company_id === $id &&
            DB::table('company_user')->where('company_id', $id)->where('user_id', auth()->id())->exists(), 404);
        return $managedCompany;
    }

    private function batch(ManagedCompany $company, TeamImportBatch $batch): TeamImportBatch
    {
        abort_unless($batch->company_id === $company->company_id && $batch->managed_company_id === $company->id &&
            $batch->user_id === auth()->id(), 404);
        abort_if($batch->expires_at->isPast(), 410, 'Importação expirada. Envie o Excel novamente.');
        return $batch;
    }

    private function sheet(TeamImportBatch $batch, TeamSpreadsheetReader $reader): array
    {
        abort_unless(Storage::disk('local')->exists($batch->stored_path), 410, 'Arquivo temporário indisponível.');
        $path = Storage::disk('local')->path($batch->stored_path);
        abort_unless(hash_file('sha256', $path) === $batch->sha256, 409, 'Arquivo alterado desde o envio.');
        return $reader->read($path);
    }

    public function upload(Request $request, ManagedCompany $managedCompany, TeamSpreadsheetReader $reader)
    {
        $company = $this->scopedCompany($managedCompany);
        $request->validate(['file'=>['required','file','mimes:xlsx,xls,csv','max:10240']]);
        $uploaded = $request->file('file');
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $path = $uploaded->storeAs('sae/team-imports', Str::uuid().'.'.$extension, 'local');
        try { $sheet = $reader->read(Storage::disk('local')->path($path)); }
        catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages(['file'=> 'Não foi possível ler a planilha: '.$e->getMessage()]);
        }
        $previous = TeamImportBatch::where('company_id', $company->company_id)
            ->where('managed_company_id', $company->id)
            ->where('status', 'committed')->latest('committed_at')->first();
        $batch = TeamImportBatch::create([
            'company_id'=>$company->company_id, 'managed_company_id'=>$company->id, 'user_id'=>auth()->id(),
            'original_name'=>mb_substr($uploaded->getClientOriginalName(), 0, 255), 'stored_path'=>$path,
            'sha256'=>hash_file('sha256', Storage::disk('local')->path($path)),
            'expires_at'=>now()->addHours(24), 'status'=>'uploaded',
        ]);
        return response()->json([
            'batch_uuid'=>$batch->uuid,'sheet'=>$sheet['sheet'],'headers'=>$sheet['headers'],
            'suggested_mapping'=>$reader->suggest($sheet['headers']),
            'records_count'=>count($sheet['records']), 'expires_at'=>$batch->expires_at, 'sha256'=>$batch->sha256,
            'previous_import'=> $previous ? ['original_name'=>$previous->original_name, 'committed_at'=>$previous->committed_at] : null,
        ],201);
    }

    private function validateMapping(array $mapping, array $headers): array
    {
        $allowed = array_merge(TeamSpreadsheetReader::FIELDS, ['aso_columns']);
        $clean = [];
        foreach ($allowed as $field) {
            if ($field === 'aso_columns') {
                $cols = $mapping[$field] ?? [];
                if (!is_array($cols) || count($cols) > 30) throw ValidationException::withMessages(['mapping'=>'Colunas ASO inválidas.']);
                $clean[$field] = array_values(array_unique(array_map('intval', $cols)));
                foreach ($clean[$field] as $index) if ($index < 0 || $index >= count($headers)) throw ValidationException::withMessages(['mapping'=>'Coluna ASO inválida.']);
            } else {
                $val = $mapping[$field] ?? null;
                if ($val === '' || $val === null) { $clean[$field] = null; continue; }
                if (!is_numeric($val) || (int)$val < 0 || (int)$val >= count($headers)) throw ValidationException::withMessages(['mapping'=>'Índice inválido para '.$field]);
                $clean[$field] = (int) $val;
            }
        }
        if ($clean['cpf'] === null || $clean['name'] === null || $clean['admitted_at'] === null) {
            throw ValidationException::withMessages(['mapping'=>'Mapeie ao menos Nome, CPF e Admissão para importar vínculos.']);
        }
        if (count(array_unique([$clean['cpf'],$clean['name'],$clean['admitted_at']])) !== 3) {
            throw ValidationException::withMessages(['mapping'=>'Nome, CPF e Admissão devem apontar para colunas diferentes.']);
        }
        return $clean;
    }

    public function preview(Request $request, ManagedCompany $managedCompany, TeamImportBatch $teamImportBatch, TeamSpreadsheetReader $reader, TeamImportAnalyzer $analyzer)
    {
        $company = $this->scopedCompany($managedCompany);
        $batch = $this->batch($company, $teamImportBatch);
        abort_if($batch->status === 'committed', 409, 'Importação já confirmada.');
        $request->validate(['mapping'=>['required','array']]);
        $sheet = $this->sheet($batch, $reader);
        $mapping = $this->validateMapping($request->input('mapping'), $sheet['headers']);
        $analysis = $analyzer->analyze($sheet, $mapping, $company, $batch->corrections ?? []);
        $batch->update(['mapping'=>$mapping,'status'=>'previewed']);
        return response()->json($analysis);
    }

    public function corrections(Request $request, ManagedCompany $managedCompany, TeamImportBatch $teamImportBatch, TeamSpreadsheetReader $reader, TeamImportAnalyzer $analyzer)
    {
        $company = $this->scopedCompany($managedCompany);
        $batch = $this->batch($company, $teamImportBatch);
        abort_unless($batch->status === 'previewed' && $batch->mapping, 409, 'Gere a prévia antes de editar.');
        $validated = $request->validate([
            'row' => ['required','integer','min:1'],
            'data' => ['required','array'],
            'data.name' => ['sometimes','nullable','string','max:255'],
            'data.cpf' => ['sometimes','nullable','string','max:20'],
            'data.registration' => ['sometimes','nullable','string','max:255'],
            'data.job_title' => ['sometimes','nullable','string','max:255'],
            'data.department' => ['sometimes','nullable','string','max:255'],
            'data.admitted_at' => ['sometimes','nullable','string','max:30'],
            'data.terminated_at' => ['sometimes','nullable','string','max:30'],
            'data.situation' => ['sometimes','nullable','string','max:30'],
        ]);
        $allowed = TeamSpreadsheetReader::FIELDS;
        $new = array_intersect_key($validated['data'], array_flip($allowed));
        abort_if(count($validated['data']) !== count($new), 422, 'Campo não permitido.');
        $sheet = $this->sheet($batch, $reader);
        $exists = collect($sheet['records'])->contains(fn ($r) => $r['row'] === (int)$validated['row']);
        abort_unless($exists, 422, 'Linha não encontrada na planilha.');
        $corrections = $batch->corrections ?? [];
        $key = (string)$validated['row'];
        $corrections[$key] = array_merge($corrections[$key] ?? [], $new);
        $batch->update(['corrections'=>$corrections]);
        // Reanalisar todo o lote: correções podem afetar duplicidades em outras linhas.
        return response()->json($analyzer->analyze($sheet, $batch->mapping, $company, $corrections));
    }

    public function commit(Request $request, ManagedCompany $managedCompany, TeamImportBatch $teamImportBatch, TeamSpreadsheetReader $reader, TeamImportAnalyzer $analyzer, ActivityLogger $logger)
    {
        $company = $this->scopedCompany($managedCompany);
        $batch = $this->batch($company, $teamImportBatch);
        $request->validate(['rows'=>['required','array','max:5000'],'rows.*'=>['required','integer','min:1']]);
        // Cada importação só pode ser efetivada uma vez. Reavaliamos o banco dentro da transação.
        $sheet = $this->sheet($batch, $reader);
        $selected = array_values(array_unique(array_map('intval', $request->input('rows', []))));
        $result = DB::transaction(function () use ($company, $batch, $sheet, $selected, $analyzer, $reader) {
            $locked = TeamImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'previewed' && $locked->mapping, 409, 'Prévia inexistente ou lote já confirmado.');
            $analysis = $analyzer->analyze($sheet, $locked->mapping, $company, $locked->corrections ?? []);
            $indexed = collect($analysis['rows'])->keyBy('row');
            $counts = ['created_employees'=>0,'created_links'=>0,'ignored'=>0];
            foreach ($selected as $number) {
                $item = $indexed->get($number);
                if (!$item) throw ValidationException::withMessages(['rows'=>'Linha '.$number.' não existe neste arquivo.']);
                if (!in_array($item['status'], ['new_employee','new_link'], true)) throw ValidationException::withMessages(['rows'=>'Linha '.$number.' mudou ou exige revisão. Refaça a prévia.']);
            }
            foreach ($selected as $number) {
                $item = $indexed[$number]['data'];
                $employee = Employee::where('company_id', $company->company_id)->where('cpf', $item['cpf'])->lockForUpdate()->first();
                if (!$employee) {
                    $employee = Employee::create(['company_id'=>$company->company_id,'cpf'=>$item['cpf'],'name'=>$item['name']]);
                    $counts['created_employees']++;
                }
                // Não atualizamos os dados de pessoas já existentes sem revisão explícita.
                $employee->links()->create([
                    'company_id'=>$company->company_id,'managed_company_id'=>$company->id,
                    'registration'=>$item['registration'],'job_title'=>$item['job_title'], 'department'=>$item['department'],
                    'admitted_at'=>$item['admitted_at'],'terminated_at'=>$item['terminated_at'], 'is_away'=>$item['is_away'],
                    'away_since'=>null, // Data real de afastamento não consta necessariamente no Excel.
                ]);
                $counts['created_links']++;
            }
            $counts['ignored'] = $analysis['summary']['total'] - count($selected);
            $locked->update(['status'=>'committed','result'=>$counts,'committed_at'=>now()]);
            return $counts;
        }, 3);
        $logger->log('team_import.committed', $batch, newValues: $result, companyId:$company->company_id);
        // Preservar o arquivo até a expiração do lote para a etapa de importação de ASOs.
        return response()->json(['message'=>'Importação concluída.','result'=>$result,'aso_imported'=>0]);
    }
}
