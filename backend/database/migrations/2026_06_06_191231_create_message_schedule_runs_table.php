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
        Schema::create('message_schedule_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('message_schedule_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->timestamp('scheduled_for');

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->string('status')->default('pending');

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique(
                ['message_schedule_id', 'scheduled_for'],
                'msr_schedule_scheduled_for_unique'
            );

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_schedule_runs');
    }
};
