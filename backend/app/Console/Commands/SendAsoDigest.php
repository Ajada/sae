<?php

namespace App\Console\Commands;

use App\Services\AsoMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendAsoDigest extends Command
{
    protected $signature = 'sae:notify-asos {--dry-run : Mostra totais sem enviar mensagens}';
    protected $description = 'Envia um resumo diário de ASOs vencidos e a vencer a cada usuário da conta assinante';

    public function handle(AsoMonitoringService $monitor): int
    {
        $today = now(config('app.timezone', 'America/Sao_Paulo'))->toDateString();
        $tenantIds = DB::table('companies')->whereNull('deleted_at')->where('is_active', true)->pluck('id');
        foreach ($tenantIds as $tenantId) {
            $report = $monitor->report((int) $tenantId);
            $alerts = array_values(array_filter($report['companies'], fn ($item) =>
                $item['counts']['upcoming'] > 0 || $item['counts']['overdue'] > 0));
            if (!$alerts) continue;
            $users = DB::table('company_user')->join('users','users.id','=','company_user.user_id')
                ->where('company_user.company_id', $tenantId)->whereNotNull('users.email')
                ->select('users.id','users.name','users.email')->distinct()->get();
            foreach ($users as $user) {
                if ($this->option('dry-run')) {
                    $this->line("Conta {$tenantId}: {$user->email}, empresas com pendência: ".count($alerts));
                    continue;
                }
                // Unique per tenant+recipient+day. Reserve before sending to prevent concurrent duplicates.
                $inserted = DB::table('aso_alert_deliveries')->insertOrIgnore([
                    'company_id' => $tenantId, 'user_id' => $user->id, 'period_date' => $today,
                    'fingerprint' => hash('sha256', json_encode($alerts)),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if (!$inserted) continue;
                try {
                    $lines = ["Resumo do SAE — {$today}", '', 'Empresas com ASOs vencidos ou próximos do vencimento:', ''];
                    foreach ($alerts as $item) {
                        $name = str_replace(["\n", "\r"], ' ', $item['company']['name']);
                        $lines[] = "{$name}: {$item['counts']['overdue']} vencido(s), {$item['counts']['upcoming']} a vencer";
                    }
                    $lines[] = '';
                    $lines[] = 'Acesse o SAE para conferir os colaboradores e os registros.';
                    $lines[] = 'Este aviso não confirma que o exame não foi realizado; baseia-se nos ASOs cadastrados.';
                    Mail::raw(implode("\n", $lines), function ($message) use ($user) {
                        $message->to($user->email)->subject('SAE — Pendências de ASOs');
                    });
                    DB::table('aso_alert_deliveries')->where('company_id', $tenantId)
                        ->where('user_id', $user->id)->where('period_date', $today)
                        ->update(['sent_at' => now(), 'updated_at' => now()]);
                    $this->info("Resumo enviado: {$user->email}");
                } catch (Throwable $exception) {
                    // Release reservation for next retry. SMTP delivery might have succeeded before an exception:
                    // for strict exactly-once delivery use an external transactional message provider.
                    DB::table('aso_alert_deliveries')->where('company_id', $tenantId)
                        ->where('user_id', $user->id)->where('period_date', $today)
                        ->whereNull('sent_at')->delete();
                    report($exception);
                    $this->error("Falha no envio para {$user->email}");
                }
            }
        }
        return self::SUCCESS;
    }
}
