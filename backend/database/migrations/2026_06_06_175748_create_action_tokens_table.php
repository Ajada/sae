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
        Schema::create('action_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('action');

            $table->string('token_hash')->unique();

            $table->string('source_type');
            $table->unsignedBigInteger('source_id');

            $table->string('recipient_email')->nullable();

            $table->json('meta')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'action']);
            $table->index(['source_type', 'source_id']);
            $table->index(['expires_at', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_tokens');
    }
};
