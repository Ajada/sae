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
        Schema::create('reminder_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('reminder_due_date_id')
                ->constrained('reminder_due_dates')
                ->cascadeOnDelete();

            $table->string('type')->default('days_before');

            $table->integer('days_before')->nullable();
            $table->date('exact_date')->nullable();

            $table->boolean('notify_on_due_date')->default(false);

            $table->boolean('repeat_after_due')->default(false);
            $table->unsignedInteger('repeat_interval_days')->nullable();
            $table->boolean('repeat_until_resolved')->default(true);
            $table->unsignedInteger('expires_after_days')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['reminder_due_date_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_alert_rules');
    }
};
