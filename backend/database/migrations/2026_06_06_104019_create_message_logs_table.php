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
        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('message_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('message_recipient_id')
                ->nullable()
                ->constrained('message_recipients')
                ->nullOnDelete();

            $table->foreignId('provider_id')
                ->nullable()
                ->constrained('delivery_providers')
                ->nullOnDelete();

            $table->string('status');

            $table->unsignedInteger('attempt')->default(1);

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['message_id', 'message_recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
