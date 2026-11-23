<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmploymentLink;
use App\Models\ManagedCompany;

class TeamImportAnalyzer
{
    public function analyze(array $spreadsheet, array $mapping, ManagedCompany $company, array $corrections = []): array
    {
        $rows = [];
        $seen = [];
        $seenCurrent = [];
        $cpfs = [];
        foreach ($spreadsheet['records'] as $record) {
            $cpf = preg_replace('/\D/', '', $corrections[(string)$record['row']]['cpf'] ?? $this->value($record['cells'], $mapping['cpf'] ?? null));
            if (strlen($cpf) === 11) $cpfs[] = $cpf;
        }
        $employees = Employee::query()->where('company_id', $company->company_id)
            ->whereIn('cpf', array_unique($cpfs))->with(['links' => fn ($q) => $q->where('managed_company_id', $company->id)])->get()->keyBy('cpf');
        $registrations = EmploymentLink::query()->where('managed_company_id', $company->id)
            ->whereNotNull('registration')->get()->keyBy('registration');
        $counts = ['new_employee'=>0,'new_link'=>0,'existing'=>0,'review'=>0];
        foreach ($spreadsheet['records'] as $record) {
            $cells = $record['cells'];
            // Correções são aplicadas apenas na memória; a planilha original permanece intacta.
            // Campos corrigidos são reavaliados por todas as validações do analisador.
            foreach (($corrections[(string)$record['row']] ?? []) as $field => $replacement) {
                $index = $mapping[$field] ?? null;
                if ($index !== null && $index !== '') $cells[(int)$index] = (string)$replacement;
            }
            $cpf = preg_replace('/\D/', '', $corrections[(string)$record['row']]['cpf'] ?? $this->value($cells, $mapping['cpf'] ?? null));
            $name = trim($corrections[(string)$record['row']]['name'] ?? $this->value($cells, $mapping['name'] ?? null));
            $registration = trim($corrections[(string)$record['row']]['registration'] ?? $this->value($cells, $mapping['registration'] ?? null));
            $admissionRaw = $corrections[(string)$record['row']]['admitted_at'] ?? $this->value($cells, $mapping['admitted_at'] ?? null);
            $terminationRaw = $corrections[(string)$record['row']]['terminated_at'] ?? $this->value($cells, $mapping['terminated_at'] ?? null);
            $admission = $this->date($admissionRaw);
            $termination = $this->date($terminationRaw);
            $situation = (new TeamSpreadsheetReader())->norm($corrections[(string)$record['row']]['situation'] ?? $this->value($cells, $mapping['situation'] ?? null));
            $data = ['name'=>$name, 'cpf'=>$cpf, 'registration'=>$registration ?: null,
                'job_title'=>trim($corrections[(string)$record['row']]['job_title'] ?? $this->value($cells, $mapping['job_title'] ?? null)) ?: null,
                'department'=>trim($corrections[(string)$record['row']]['department'] ?? $this->value($cells, $mapping['department'] ?? null)) ?: null,
                'admitted_at'=>$admission, 'terminated_at'=>$termination,
                'is_away'=>in_array($situation, ['afastado', 'afastada'], true)];
            $errors = [];
            if (!$name) $errors[] = 'Nome obrigatório';
            if (!$this->validCpf($cpf)) $errors[] = 'CPF ausente ou inválido';
            if (!$admission) $errors[] = 'Admissão ausente ou inválida';
            if (trim($terminationRaw) !== '' && !in_array(mb_strtolower(trim($terminationRaw)), ['-','na','n/a'], true) && !$termination) $errors[] = 'Demissão inválida';
            if ($admission && $termination && $termination < $admission) $errors[] = 'Demissão anterior à admissão';
            if ($data['is_away'] && $termination) $errors[] = 'Vínculo desligado não pode permanecer afastado';
            if (in_array($situation, ['desligado','desativado','demitido'], true) && !$termination) $errors[] = 'Situação desligado sem data de demissão';
            if (isset($seen[$cpf.'|'.$registration.'|'.$admission]) && $cpf !== '') $errors[] = 'Linha duplicada no arquivo';
            $seen[$cpf.'|'.$registration.'|'.$admission] = true;
            if ($cpf !== '' && !$termination) {
                if (isset($seenCurrent[$cpf])) $errors[] = 'Mais de um vínculo vigente para o CPF na planilha';
                $seenCurrent[$cpf] = true;
            }
            $employee = $employees->get($cpf);
            $matched = null;
            if ($employee) {
                $links = $employee->links;
                if ($registration) $matched = $links->firstWhere('registration', $registration);
                elseif ($admission) $matched = $links->first(fn ($l) => $l->admitted_at?->format('Y-m-d') === $admission);
                if ($registration && isset($registrations[$registration]) && $registrations[$registration]->employee_id !== $employee->id) $errors[] = 'Matrícula pertence a outro CPF nesta empresa';
                if ($matched && $admission && $matched->admitted_at?->format('Y-m-d') !== $admission) $errors[] = 'Matrícula existente com outra data de admissão';
                if ($matched && $matched->terminated_at?->format('Y-m-d') !== $termination) $errors[] = 'Data de demissão difere do cadastro existente';
                if (!$matched && !$termination && $links->contains(fn ($l) => $l->terminated_at === null)) $errors[] = 'Já existe vínculo vigente nesta empresa';
                if (!$matched && !$registration && $admission && $links->contains(fn ($l) => $l->admitted_at?->format('Y-m-d') === $admission)) $errors[] = 'Admissão ambígua; confira vínculo existente';
            }
            $status = $errors ? 'review' : ($matched ? 'existing' : ($employee ? 'new_link' : 'new_employee'));
            $counts[$status]++;
            $aso = [];
            foreach (($mapping['aso_columns'] ?? []) as $col) {
                $raw = $this->value($cells, $col);
                if ($raw !== '') $aso[] = ['column'=>$spreadsheet['headers'][$col] ?? 'ASO', 'value'=>$raw];
            }
            $rows[] = ['row'=>$record['row'],'data'=>$data,'status'=>$status,'issues'=>$errors,'aso_preview'=>$aso];
        }
        return ['rows'=>$rows,'summary'=>array_merge(['total'=>count($rows)], $counts), 'aso_note'=>'Datas e estados de ASO são apenas exibidos; não são gravados nesta etapa.'];
    }

    private function value(array $cells, $index): string { return $index === null || $index === '' ? '' : trim((string) ($cells[(int)$index] ?? '')); }

    private function date(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || in_array(mb_strtolower($value), ['-','na','n/a'], true)) return null;
        foreach (['!d/m/Y','!d-m-Y','!Y-m-d','!d/m/y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date && $date->format(substr($format, 1)) === $value) return $date->format('Y-m-d');
        }
        if (preg_match('/^\d+(?:\.0)?$/', $value) && (float)$value > 20000 && (float)$value < 100000) {
            try { return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('Y-m-d'); } catch (\Throwable) {}
        }
        return null;
    }

    private function validCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($n = 9; $n <= 10; $n++) {
            $sum = 0;
            for ($i=0; $i<$n; $i++) $sum += (int)$cpf[$i] * ($n+1-$i);
            $digit = ($sum*10)%11;
            if (($digit === 10 ? 0 : $digit) !== (int)$cpf[$n]) return false;
        }
        return true;
    }
}
