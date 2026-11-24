<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // NULL significa status do envio desconhecido; diferente de false (não transmitido).
        DB::statement('ALTER TABLE asos MODIFY esocial_submitted TINYINT(1) NULL DEFAULT NULL');
    }
    public function down(): void
    {
        // Sem reversão automática: poderia apagar a distinção de registros desconhecidos.
    }
};
