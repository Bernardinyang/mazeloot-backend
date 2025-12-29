<?php

use App\Domains\Memora\Enums\MediaFeedbackTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Check if table exists - if so, add missing columns; if not, create it
        if (Schema::hasTable('memora_media_feedback')) {
            // Table exists, add missing columns
            Schema::table('memora_media_feedback', static function (Blueprint $table) {
                // Add parent_uuid column if it doesn't exist
                if (!Schema::hasColumn('memora_media_feedback', 'parent_uuid')) {
                    $table->uuid('parent_uuid')->nullable()->after('media_uuid');
                    $table->foreign('parent_uuid')
                        ->references('uuid')
                        ->on('memora_media_feedback')
                        ->onDelete('cascade');
                    $table->index('parent_uuid');
                }
                
                // Add timestamp column if it doesn't exist
                if (!Schema::hasColumn('memora_media_feedback', 'timestamp')) {
                    $table->decimal('timestamp', 10, 2)->nullable()->after('parent_uuid');
                }
                
                // Add mentions column if it doesn't exist
                if (!Schema::hasColumn('memora_media_feedback', 'mentions')) {
                    $table->json('mentions')->nullable()->after('timestamp');
                }
            });
        } else {
            // Table doesn't exist, create it with all columns
            Schema::create('memora_media_feedback', static function (Blueprint $table) {
                $table->unsignedBigInteger('id')->nullable();
                $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
                $table->foreignUuid('media_uuid')->constrained('memora_media', 'uuid')->cascadeOnDelete();
                $table->uuid('parent_uuid')->nullable(); // For reply threading
                $table->decimal('timestamp', 10, 2)->nullable(); // Video comment timestamp in seconds
                $table->json('mentions')->nullable(); // Mentioned email addresses
                $table->enum('type', MediaFeedbackTypeEnum::values());
                $table->text('content'); // Text content or URL for video/audio
                $table->json('created_by')->nullable(); // client-identifier
                $table->timestamps();

                // Foreign key for parent comment (self-referencing)
                $table->foreign('parent_uuid')
                    ->references('uuid')
                    ->on('memora_media_feedback')
                    ->onDelete('cascade');

                // Index for efficient reply queries
                $table->index('parent_uuid');
                $table->index('media_uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('memora_media_feedback');
    }
};
