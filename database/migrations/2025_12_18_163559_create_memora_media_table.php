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
        Schema::create('memora_media', static function (Blueprint $table) {
            // Primary keys
            $table->unsignedBigInteger('id')->nullable(); // Legacy numeric ID (nullable)
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())')); // Primary UUID identifier

            // Foreign keys
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete(); // Owner of the media
            $table->foreignUuid('media_set_uuid')->constrained('memora_media_sets', 'uuid')->cascadeOnDelete(); // Media set this belongs to
            $table->foreignUuid('original_media_uuid')->nullable()->constrained('memora_media', 'uuid')->nullOnDelete(); // Original media UUID for revisions (self-referencing)
            $table->foreignUuid('user_file_uuid')->nullable()->constrained('user_files', 'uuid')->nullOnDelete(); // Reference to uploaded file

            // Selection status
            $table->boolean('is_selected')->default(false); // Whether this media is selected in a selection
            $table->timestamp('selected_at')->nullable(); // Timestamp when media was selected

            // Revision tracking
            $table->integer('revision_number')->nullable(); // Revision number (null for original, 1, 2, 3... for revisions)
            $table->boolean('is_ready_for_revision')->default(false); // Flag indicating media is ready for revision (after approved closure request)
            $table->boolean('is_revised')->default(false); // Flag indicating this revision has been superseded by a newer revision
            $table->text('revision_description')->nullable(); // Description of changes made in this revision
            $table->json('revision_todos')->nullable(); // Array of todos that were completed when this revision was uploaded

            // Completion status
            $table->boolean('is_completed')->default(false); // Whether this media item is marked as completed
            $table->timestamp('completed_at')->nullable(); // Timestamp when media was marked as completed

            // Media metadata (stored here for quick access, also available via user_file relationship)
            $table->string('url')->nullable(); // Direct URL to media file
            $table->string('type')->nullable(); // Media type (image/video)
            $table->string('filename')->nullable(); // Original filename
            $table->string('mime_type')->nullable(); // MIME type of the file
            $table->unsignedBigInteger('size')->nullable(); // File size in bytes
            $table->unsignedInteger('width')->nullable(); // Image/video width in pixels
            $table->unsignedInteger('height')->nullable(); // Image/video height in pixels

            // Ordering
            $table->integer('order')->default(0); // Display order within the media set

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
        Schema::dropIfExists('memora_media');
    }
};
