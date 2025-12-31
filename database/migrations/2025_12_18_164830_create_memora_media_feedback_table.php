<?php

use App\Domains\Memora\Enums\MediaFeedbackTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if table exists - if so, add missing columns; if not, create it
        if (Schema::hasTable('memora_media_feedback')) {
            // Table exists, add missing columns
            Schema::table('memora_media_feedback', static function (Blueprint $table) {
                // Add parent_uuid column if it doesn't exist
                if (! Schema::hasColumn('memora_media_feedback', 'parent_uuid')) {
                    $table->uuid('parent_uuid')->nullable()->after('media_uuid');
                    $table->foreign('parent_uuid')
                        ->references('uuid')
                        ->on('memora_media_feedback')
                        ->onDelete('cascade');
                    $table->index('parent_uuid');
                }

                // Add timestamp column if it doesn't exist
                if (! Schema::hasColumn('memora_media_feedback', 'timestamp')) {
                    $table->decimal('timestamp', 10, 2)->nullable()->after('parent_uuid');
                }

                // Add mentions column if it doesn't exist
                if (! Schema::hasColumn('memora_media_feedback', 'mentions')) {
                    $table->json('mentions')->nullable()->after('timestamp');
                }
            });
        } else {
            // Table doesn't exist, create it with all columns
            Schema::create('memora_media_feedback', static function (Blueprint $table) {
                // Primary keys
                $table->unsignedBigInteger('id')->nullable(); // Legacy numeric ID (nullable)
                $table->uuid('uuid')->primary()->default(DB::raw('(UUID())')); // Primary UUID identifier

                // Foreign keys
                $table->foreignUuid('media_uuid')->constrained('memora_media', 'uuid')->cascadeOnDelete(); // Media item this feedback belongs to
                $table->uuid('parent_uuid')->nullable(); // UUID of parent comment for reply threading (self-referencing)

                // Feedback content
                $table->enum('type', MediaFeedbackTypeEnum::values()); // Type of feedback (comment, annotation, etc.)
                $table->text('content'); // Text content or URL for video/audio feedback
                $table->decimal('timestamp', 10, 2)->nullable(); // Video comment timestamp in seconds (for video annotations)

                // Mentions and author
                $table->json('mentions')->nullable(); // Array of mentioned email addresses
                $table->json('created_by')->nullable(); // Client identifier or user info stored as JSON

                // Timestamps
                $table->timestamps(); // created_at, updated_at

                // Foreign key for parent comment (self-referencing)
                $table->foreign('parent_uuid')
                    ->references('uuid')
                    ->on('memora_media_feedback')
                    ->onDelete('cascade');

                // Indexes for efficient queries
                $table->index('parent_uuid'); // For fetching replies to a comment
                $table->index('media_uuid'); // For fetching all feedback for a media item
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memora_media_feedback');
    }
};
