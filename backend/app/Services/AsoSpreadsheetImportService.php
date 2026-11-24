<?php

namespace App\Services;

use App\Models\Aso;
use App\Models\Employee;
use App\Models\EmploymentLink;
use App\Models\ManagedCompany;
use App\Models\TeamImportBatch;
use Carbon\CarbonImmutable;

class AsoSpreadsheetImportService
{
    public function preview(array $sheet, TeamImportBatch $batch, ManagedCompany $company, int $months): array
    {
        $mapping = $batch->mapping ?? [];
        $corrections = $batch->corrections ?? [];
        $selectedColumns = array_map('intval', $mapping['aso_columns'] ?? []);
        $out = [];
        $summary = ['new' => 0, 'existing' => 0, 'review' => 0, 'ignored' => 0];
        $cache = [];

        foreach ($sheet['records'] as $record) {
            $row = (int) $record['row'];
            $cells = $record['cells'];
            $fix = $corrections[(string) $row] ?? [];
            $cpf = preg_replace('/\D/', '', (string) ($fix['cpf'] ?? $this->cell($cells, $mapping['cpf'] ?? null)));
            $reg = trim((string) ($fix['registration'] ?? $this->cell($cells, $mapping['registration'] ?? null)));
            $admitted = $this->parseSingleDate((string) ($fix['admitted_at'] ?? $this->cell($cells, $mapping['admitted_at'] ?? null)));
            $name = trim((string) ($fix['name'] ?? $this->cell($cells, $mapping['name'] ?? null)));

            $links = collect();
            if (strlen($cpf) === 11) {
                if (!array_key_exists($cpf, $cache)) {
                    $employee = Employee::where('company_id', $company->company_id)->where('cpf', $cpf)->first();
                    $cache[$cpf] = $employee ? EmploymentLink::where('company_id', $company->company_id)
                        ->where('managed_company_id', $company->id)->where('employee_id', $employee->id)->get() : collect();
                }
                $links = $cache[$cpf];
            }
            $matching = $reg !== '' ? $links->filter(fn ($l) => $l->registration === $reg)
                : $links->filter(fn ($l) => $admitted && $l->admitted_at?->format('Y-m-d') === $admitted);
            $link = $matching->count() === 1 ? $matching->first() : null;

            foreach ($selectedColumns as $col) {
                $header = trim((string) ($sheet['headers'][$col] ?? ''));
                $raw = $this->cell($cells, $col);
                if (trim($raw) === '') continue;
                $dates = $this->dates($raw);
                $type = $this->type($header);
                if (!$dates) {
                    if (preg_match('/^(NA|N\/A|NÃO APLICÁVEL|NAO APLICAVEL|A VENCER|PENDENTE|DISPENSADO|VERIFICAR|[-])\b/iu', trim($raw)) || trim($raw) === '-') {
                        $summary['ignored']++;
                    } else {
                        $out[] = $this->entry($row, $col, '', $type, 'review', ['Conteúdo não reconhecido como data de ASO'], $cpf, $name, $header, $raw, null, null);
                        $summary['review']++;
                    }
                    continue;
                }
                foreach ($dates as $date) {
                    $issues = [];
                    if (!$type) $issues[] = 'Tipo de ASO não identificado no cabeçalho';
                    if (!$link) $issues[] = 'Vínculo não identificado de forma única; confira matrícula/admissão';
                    if ($date > now()->toDateString()) $issues[] = 'Data de realização no futuro';
                    if ($admitted && $date < $admitted && $type !== 'admissional') $issues[] = 'ASO anterior à admissão; revisar vínculo';
                    $existing = false;
                    if (!$issues) {
                        $existing = Aso::where('company_id', $company->company_id)
                            ->where('employment_link_id', $link->id)->where('exam_type', $type)
                            ->whereDate('performed_at', $date)->exists();
                    }
                    $status = $issues ? 'review' : ($existing ? 'existing' : 'new');
                    $summary[$status]++;
                    $due = $type === 'demissional' ? null : CarbonImmutable::parse($date)->addMonthsNoOverflow($months)->toDateString();
                    $out[] = $this->entry($row, $col, $date, $type, $status, $issues, $cpf, $name, $header, $raw, $link?->id, $due);
                }
            }
        }
        // Never import the same tuple twice from distinct lines or columns.
        $seen = [];
        foreach ($out as &$item) {
            if (!$item['link_id'] || !$item['exam_type'] || !$item['performed_at']) continue;
            $key = $item['link_id'].'|'.$item['exam_type'].'|'.$item['performed_at'];
            if (isset($seen[$key]) && $item['status'] === 'new') {
                $item['status'] = 'review';
                $item['issues'][] = 'Exame duplicado na própria planilha';
                $summary['new']--;
                $summary['review']++;
            }
            $seen[$key] = true;
        }
        unset($item);
        return ['file' => $batch->original_name, 'months' => $months, 'rows' => $out, 'summary' => $summary,
            'notice' => 'A validade prevista foi calculada com a periodicidade escolhida; confira o PCMSO. Transmissão ao eSocial: não informada.'];
    }

    private function entry(int $row, int $col, string $date, ?string $type, string $status, array $issues,
        string $cpf, string $name, string $header, string $raw, ?int $link, ?string $due): array
    {
        $key = hash('sha256', implode('|', [$row, $col, $date, $type ?? '']));
        return ['key' => $key, 'row' => $row, 'column' => $col, 'header' => $header, 'raw' => $raw,
            'cpf' => $cpf, 'name' => $name, 'exam_type' => $type, 'performed_at' => $date,
            'next_due_at' => $due, 'status' => $status, 'issues' => $issues, 'link_id' => $link];
    }

    private function cell(array $row, $index): string
    {
        return $index === null ? '' : trim((string) ($row[(int) $index] ?? ''));
    }

    private function type(string $header): ?string
    {
        $h = mb_strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $header) ?: $header);
        if (str_contains($h, 'demission')) return 'demissional';
        if (str_contains($h, 'admission')) return 'admissional';
        if (str_contains($h, 'periodic')) return 'periodico';
        if (str_contains($h, 'retorno')) return 'retorno';
        if (str_contains($h, 'mudanca') || str_contains($h, 'alteracao de risco')) return 'mudanca_risco';
        return null;
    }

    private function parseSingleDate(string $value): ?string
    {
        foreach (['!d/m/Y', '!d-m-Y', '!Y-m-d', '!d/m/y'] as $format) {
            $d = \DateTimeImmutable::createFromFormat($format, trim($value));
            if ($d && $d->format(substr($format, 1)) === trim($value)) return $d->format('Y-m-d');
        }
        return null;
    }

    private function dates(string $raw): array
    {
        preg_match_all('/(?<!\d)(\d{2}[\/\-]\d{2}[\/\-]\d{4}|\d{4}-\d{2}-\d{2})(?!\d)/', $raw, $m);
        $result = [];
        foreach ($m[1] as $candidate) {
            $value = $this->parseSingleDate($candidate);
            if ($value) $result[] = $value;
        }
        return array_values(array_unique($result));
    }
}
