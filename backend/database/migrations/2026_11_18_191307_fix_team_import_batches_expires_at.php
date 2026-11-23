<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE team_import_batches
            MODIFY COLUMN expires_at DATETIME NOT NULL
        ");
    }

    public function down(): void
    {
        // Sem reversão automática para evitar
        // reintroduzir o comportamento anterior.
    }
};