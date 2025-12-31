<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memora_closure_requests', static function (Blueprint $table) {
            // Primary keys
            $table->unsignedBigInteger('id')->nullable(); // Legacy numeric ID (nullable)
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())')); // Primary UUID identifier

            // Foreign keys
            $table->foreignUuid('proofing_uuid')->constrained('memora_proofing', 'uuid')->cascadeOnDelete(); // Proofing phase this closure request belongs to
            $table->foreignUuid('media_uuid')->constrained('memora_media', 'uuid')->cascadeOnDelete(); // Media item this closure request is for
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete(); // User who created the closure request

            // Request details
            $table->string('token')->unique(); // Unique token for public access to closure request
            $table->json('todos')->nullable(); // Array of action items/todos that need to be completed

            // Status tracking
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending'); // Current status of the closure request
            $table->timestamp('approved_at')->nullable(); // Timestamp when request was approved
            $table->timestamp('rejected_at')->nullable(); // Timestamp when request was rejected
            $table->string('approved_by_email')->nullable(); // Email of user who approved the request
            $table->string('rejected_by_email')->nullable(); // Email of user who rejected the request
            $table->text('rejection_reason')->nullable(); // Reason provided when rejecting the closure request

            // Timestamps
            $table->timestamps(); // created_at, updated_at
            $table->softDeletes(); // deleted_at for soft deletion
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_closure_requests');
    }
};
