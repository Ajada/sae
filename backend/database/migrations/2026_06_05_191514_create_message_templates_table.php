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
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('key')->nullable();

            $table->string('channel')->default('email');

            $table->string('subject')->nullable();

            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();

            $table->json('variables')->nullable();

            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);

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

            $table->index(['company_id', 'channel']);
            $table->index(['company_id', 'is_active']);
            $table->index(['is_system', 'channel']);
            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
