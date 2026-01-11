<?php

use App\Domains\Memora\Enums\ProofingStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memora_proofing', static function (Blueprint $table) {
            // Primary keys
            $table->unsignedBigInteger('id')->nullable(); // Legacy numeric ID (nullable)
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())')); // Primary UUID identifier

            // Foreign keys
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete(); // Owner of the proofing phase
            $table->foreignUuid('folder_uuid')->nullable()->constrained('memora_folders', 'uuid')->cascadeOnDelete(); // Folder this proofing belongs to (optional)
            $table->foreignUuid('project_uuid')->nullable()->constrained('memora_projects', 'uuid')->cascadeOnDelete(); // Project this proofing belongs to (optional)

            // Basic information
            $table->string('name'); // Name of the proofing phase
            $table->text('description')->nullable(); // Description of the proofing phase

            // Status and appearance
            $table->enum('status', ProofingStatusEnum::values())->default(ProofingStatusEnum::DRAFT->value); // Current status (draft, active, completed, etc.)
            $table->string('color', 7)->default('#F59E0B'); // Color theme for the proofing phase (hex format)

            // Cover image
            $table->string('cover_photo_url')->nullable(); // URL to cover photo
            $table->json('cover_focal_point')->nullable(); // Focal point coordinates for cover photo cropping

            // Access control
            $table->string('password')->nullable(); // Optional password for accessing proofing
            $table->json('allowed_emails')->nullable(); // Array of email addresses allowed to access this proofing
            $table->string('primary_email')->nullable(); // Primary contact email for this proofing
            $table->json('settings')->nullable(); // Settings for the proofing phase

            // Revision tracking
            $table->integer('max_revisions')->default(5); // Maximum number of revisions allowed per media item
            $table->integer('current_revision')->default(0); // Current revision number across all media

            // Completion
            $table->timestamp('completed_at')->nullable(); // Timestamp when proofing was completed

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
        Schema::dropIfExists('memora_proofing');
    }
};
