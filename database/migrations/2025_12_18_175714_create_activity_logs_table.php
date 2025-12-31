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
        Schema::create('activity_logs', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()
                ->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();

            // Polymorphic relationship to the subject (model being acted upon)
            $table->string('subject_type')->nullable();
            $table->uuid('subject_uuid')->nullable();

            // Action details
            $table->string('action'); // e.g., 'created', 'updated', 'deleted', 'viewed', 'logged_in', etc.
            $table->text('description')->nullable();
            $table->json('properties')->nullable(); // Additional metadata

            // Request details
            $table->string('route')->nullable(); // Route name or URI
            $table->string('method', 10)->nullable(); // HTTP method
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Additional context
            $table->string('causer_type')->nullable(); // For API keys, system actions, etc.
            $table->uuid('causer_uuid')->nullable();

            $table->timestamps();

            // Indexes for better query performance
            $table->index(['user_uuid', 'created_at']);
            $table->index(['subject_type', 'subject_uuid']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
