<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('managed_company_id')->constrained('managed_companies')->restrictOnDelete();
            $table->foreignId('employment_link_id')->constrained('employment_links')->restrictOnDelete();
            $table->enum('exam_type', ['admissional','periodico','retorno','mudanca_risco','demissional']);
            $table->date('performed_at');
            $table->date('next_due_at')->nullable(); // calculada/confirmada pelo operador, conforme PCMSO
            $table->boolean('esocial_submitted')->default(false);
            $table->dateTime('esocial_submitted_at')->nullable();
            $table->string('receipt_number')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employment_link_id','performed_at'], 'idx_asos_link_date');
            $table->index(['company_id','next_due_at'], 'idx_asos_tenant_due');
        });
        Schema::create('aso_alert_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('period_date');
            $table->string('fingerprint', 64);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id','user_id','period_date'], 'uq_aso_alert_daily');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('aso_alert_deliveries');
        Schema::dropIfExists('asos');
    }
};
