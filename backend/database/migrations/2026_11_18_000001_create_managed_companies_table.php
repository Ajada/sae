<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('managed_companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Conta SaaS (companies já existente), não empresa cliente.
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('corporate_name');
            $table->string('trade_name')->nullable();
            $table->char('cnpj', 14);
            $table->unsignedTinyInteger('risk_grade');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'cnpj']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'corporate_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_companies');
    }
};
