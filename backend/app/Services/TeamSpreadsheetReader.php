<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use RuntimeException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TeamSpreadsheetReader
{
    public const FIELDS = ['name','cpf','registration','job_title','department','admitted_at','terminated_at','situation'];

    public function read(string $absolutePath, int $limit = 5000): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(false);
        $workbook = $reader->load($absolutePath);
        try {
            $sheet = $workbook->getSheet(0); // Somente primeira aba; evitar abas de resumo.
            $lastRow = $sheet->getHighestDataRow();
            $lastColumn = min(Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 80);
            if ($lastRow > $limit + 30) throw new RuntimeException('O arquivo ultrapassa o limite de 5.000 linhas de dados por importação.');
            $headerRow = null;
            $fallbackRow = null;
            for ($r = 1; $r <= min(25, $lastRow); $r++) {
                $values = $this->row($sheet, $r, $lastColumn);
                $normalized = array_map(fn ($v) => $this->norm($v), $values);
                if ($fallbackRow === null && count(array_filter($values, fn ($v) => $v !== '')) >= 3) $fallbackRow = $r;
                if (in_array('cpf', $normalized, true) && (in_array('nome', $normalized, true) || in_array('colaborador', $normalized, true) || in_array('nome completo', $normalized, true))) {
                    $headerRow = $r; break;
                }
            }
            $headerRow ??= $fallbackRow;
            if (!$headerRow) throw new RuntimeException('Cabeçalho não encontrado nas primeiras 25 linhas.');
            $headers = $this->row($sheet, $headerRow, $lastColumn);
            $records = [];
            for ($r = $headerRow + 1; $r <= $lastRow; $r++) {
                $cells = $this->row($sheet, $r, $lastColumn);
                if (!count(array_filter($cells, fn ($v) => trim((string) $v) !== ''))) continue;
                $records[] = ['row' => $r, 'cells' => $cells];
                if (count($records) > $limit) throw new RuntimeException('Limite de 5.000 registros excedido.');
            }
            return ['sheet' => $sheet->getTitle(), 'header_row' => $headerRow, 'headers' => $headers, 'records' => $records];
        } finally { $workbook->disconnectWorksheets(); unset($workbook); }
    }

    private function row($sheet, int $row, int $cols): array
    {
        $values = [];
        for ($c = 1; $c <= $cols; $c++) {
            $cell = $sheet->getCell([$c, $row]);
            // Não executar fórmulas do arquivo enviado. Usar valor cacheado quando existir.
            $raw = $cell->getValue();
            if (is_string($raw) && str_starts_with($raw, '=')) {
                $raw = $cell->getOldCalculatedValue();
            } elseif (is_numeric($raw) && ExcelDate::isDateTime($cell)) {
                $raw = ExcelDate::excelToDateTimeObject((float) $raw)->format('d/m/Y');
            }
            $values[] = trim((string) ($raw ?? ''));
        }
        return $values;
    }

    public function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ', $value)));
    }

    public function suggest(array $headers): array
    {
        $dictionary = [
            'name'=>['nome','nome completo','colaborador','trabalhador','funcionario'],
            'cpf'=>['cpf','cpf colaborador','cpf trabalhador'],
            'registration'=>['matricula','matricula esocial','matricula do colaborador'],
            'job_title'=>['funcao','cargo','ocupacao'],
            'department'=>['setor','departamento'],
            'admitted_at'=>['admissao','data admissao','data de admissao'],
            'terminated_at'=>['demissao','desligamento','data demissao','data desligamento'],
            'situation'=>['situacao','status','situacao contratual'],
        ];
        $mapping = [];
        foreach ($dictionary as $field => $aliases) {
            $mapping[$field] = null;
            foreach ($headers as $i => $header) {
                if (in_array($this->norm((string) $header), $aliases, true)) { $mapping[$field] = $i; break; }
            }
        }
        $mapping['aso_columns'] = [];
        foreach ($headers as $i => $header) {
            if (preg_match('/\b(aso|periodic|admissional|demissional|mudanca de risco|retorno ao trabalho)\b/i', $this->norm((string) $header))) {
                $mapping['aso_columns'][] = $i;
            }
        }
        return $mapping;
    }
}
