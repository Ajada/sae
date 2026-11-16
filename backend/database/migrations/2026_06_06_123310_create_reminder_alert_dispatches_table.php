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
        Schema::create('reminder_alert_dispatches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('reminder_due_date_id')
                ->constrained('reminder_due_dates')
                ->cascadeOnDelete();

            $table->foreignId('reminder_alert_rule_id')
                ->constrained('reminder_alert_rules')
                ->cascadeOnDelete();

            $table->foreignId('message_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->date('trigger_date');

            $table->string('status')->default('pending');

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->unique(
                ['reminder_due_date_id', 'reminder_alert_rule_id', 'trigger_date'],
                'rad_due_rule_trigger_unique'
            );

            $table->index(['company_id', 'trigger_date']);
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_alert_dispatches');
    }
};
