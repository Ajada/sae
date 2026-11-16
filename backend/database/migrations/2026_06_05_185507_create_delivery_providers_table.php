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
        Schema::create('delivery_providers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            // email, whatsapp futuramente
            $table->string('channel')->default('email');

            // smtp, venom futuramente
            $table->string('driver')->default('smtp');

            // SMTP
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->text('password_encrypted')->nullable();
            $table->string('encryption')->nullable(); // tls, ssl ou null

            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->string('reply_to_email')->nullable();

            // Fallback
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(1);

            // Testes
            $table->timestamp('tested_at')->nullable();
            $table->text('last_error')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'channel', 'driver']);
            $table->index(['company_id', 'is_default']);
            $table->index(['company_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_providers');
    }
};
