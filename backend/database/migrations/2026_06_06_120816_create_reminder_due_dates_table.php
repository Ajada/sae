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
        Schema::create('reminder_due_dates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('reminder_item_id')
                ->constrained('reminder_items')
                ->cascadeOnDelete();

            $table->foreignId('provider_id')
                ->nullable()
                ->constrained('delivery_providers')
                ->nullOnDelete();

            $table->foreignId('template_id')
                ->nullable()
                ->constrained('message_templates')
                ->nullOnDelete();

            $table->string('label')->nullable();
            $table->date('due_date');

            $table->string('channel')->default('email');
            $table->string('status')->default('active');

            $table->boolean('allow_fallback')->default(true);

            $table->text('notes')->nullable();

            $table->timestamp('resolved_at')->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('renewed_from_id')
                ->nullable()
                ->constrained('reminder_due_dates')
                ->nullOnDelete();

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

            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'status']);
            $table->index(['reminder_item_id', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_due_dates');
    }
};
