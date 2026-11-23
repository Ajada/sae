<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('managed_company_id')->constrained('managed_companies')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('sha256', 64);
            $table->json('mapping')->nullable();
            $table->json('result')->nullable();
            $table->string('status', 20)->default('uploaded');
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'managed_company_id', 'status'], 'idx_team_imp_scope_status');
        });
    }
    public function down(): void { Schema::dropIfExists('team_import_batches'); }
};
