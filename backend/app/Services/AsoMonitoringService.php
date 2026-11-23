<?php

namespace App\Services;

use App\Models\Aso;
use App\Models\EmploymentLink;
use App\Models\ManagedCompany;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AsoMonitoringService
{
    /** O período padrão é provisório; configure pelo PCMSO antes de uso real. */
    public function report(int $tenantId, int $alertDays = 30): array
    {
        $today = CarbonImmutable::today(config('app.timezone', 'America/Sao_Paulo'));
        $limit = $today->addDays($alertDays);
        $companies = ManagedCompany::query()->where('company_id', $tenantId)->where('is_active', true)
            ->orderBy('corporate_name')->get(['id','uuid','corporate_name','trade_name']);
        $result = [];
        foreach ($companies as $company) {
            $counts = ['active' => 0, 'away' => 0, 'upcoming' => 0, 'overdue' => 0, 'without_aso' => 0, 'transmission_pending' => 0];
            // One row per current employment link. Historical links never count.
            EmploymentLink::query()->where('company_id', $tenantId)
                ->where('managed_company_id', $company->id)->whereNull('terminated_at')
                ->with(['employee:id,name,cpf'])
                ->orderBy('id')->chunkById(400, function ($links) use (&$counts, $today, $limit) {
                    $latest = Aso::query()->whereIn('employment_link_id', $links->pluck('id'))
                        ->where('exam_type', '!=', 'demissional')->whereDate('performed_at', '<=', $today->toDateString())
                        ->orderByDesc('performed_at')->orderByDesc('id')->get()
                        ->unique('employment_link_id')->keyBy('employment_link_id');
                    foreach ($links as $link) {
                        $counts[$link->is_away ? 'away' : 'active']++;
                        $aso = $latest->get($link->id);
                        if (!$aso) { $counts['without_aso']++; continue; }
                        if (!$aso->esocial_submitted) $counts['transmission_pending']++;
                        // For now, the next_due_at is required for a trustworthy expiration alert.
                        if (!$aso->next_due_at) { $counts['without_aso']++; continue; }
                        $due = CarbonImmutable::parse($aso->next_due_at);
                        if ($due->lt($today)) $counts['overdue']++;
                        elseif ($due->lte($limit)) $counts['upcoming']++;
                    }
                });
            $result[] = ['company' => ['uuid' => $company->uuid, 'name' => $company->trade_name ?: $company->corporate_name], 'counts' => $counts];
        }
        return ['date' => $today->toDateString(), 'alert_days' => $alertDays, 'companies' => $result];
    }
}
