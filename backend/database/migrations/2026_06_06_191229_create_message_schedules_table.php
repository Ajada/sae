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
        Schema::create('message_schedules', function (Blueprint $table) {
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

            $table->string('name');

            $table->string('channel')->default('email');

            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();

            $table->json('variables')->nullable();
            $table->json('metadata')->nullable();

            // daily, weekly, monthly, yearly
            $table->string('frequency');

            // 1 = segunda, 7 = domingo
            $table->unsignedTinyInteger('day_of_week')->nullable();

            // 1 até 31
            $table->unsignedTinyInteger('day_of_month')->nullable();

            // 1 até 12, usado em yearly
            $table->unsignedTinyInteger('month')->nullable();

            $table->time('send_time')->default('08:00:00');
            $table->string('timezone')->default('America/Sao_Paulo');

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('next_run_at')->nullable();

            $table->string('status')->default('active');

            $table->boolean('allow_fallback')->default(true);

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

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'next_run_at']);
            $table->index(['company_id', 'frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_schedules');
    }
};
