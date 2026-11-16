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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('provider_id')
                ->nullable()
                ->constrained('delivery_providers')
                ->nullOnDelete();

            $table->foreignId('template_id')
                ->nullable()
                ->constrained('message_templates')
                ->nullOnDelete();

            $table->string('channel')->default('email');

            // manual, reminder, api, campaign futuramente
            $table->string('source_type')->default('manual');
            $table->unsignedBigInteger('source_id')->nullable();

            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();

            $table->json('variables')->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->string('status')->default('pending');

            $table->boolean('allow_fallback')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'scheduled_at']);
            $table->index(['company_id', 'source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
