<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete(); // conta SaaS
            $table->string('name');
            $table->char('cpf', 11);
            $table->timestamps();
            $table->unique(['company_id', 'cpf']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('employment_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete(); // tenant
            $table->foreignId('managed_company_id')->constrained('managed_companies')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('registration', 80)->nullable(); // matrícula eSocial, texto para preservar zeros
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->date('admitted_at');
            $table->date('terminated_at')->nullable();
            $table->boolean('is_away')->default(false);
            $table->date('away_since')->nullable();
            $table->timestamps();
            $table->unique(['managed_company_id', 'registration']);
            $table->index(['company_id', 'managed_company_id', 'terminated_at'], 'idx_emp_links_company_status');
            $table->index(['employee_id', 'managed_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_links');
        Schema::dropIfExists('employees');
    }
};
