<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('source_id');
            $table->string('idempotency_key')->nullable()->after('external_id');
            $table->json('metadata')->nullable()->after('variables');

            $table->index(['company_id', 'external_id']);
            $table->unique(['company_id', 'idempotency_key'], 'messages_company_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_company_idempotency_unique');
            $table->dropIndex(['company_id', 'external_id']);

            $table->dropColumn([
                'external_id',
                'idempotency_key',
                'metadata',
            ]);
        });
    }
};
