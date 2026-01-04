<?php

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
        Schema::create('memora_media_sets', static function (Blueprint $table) {
            // Primary keys
            $table->unsignedBigInteger('id')->nullable(); // Legacy numeric ID (nullable)
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())')); // Primary UUID identifier

            // Foreign keys
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete(); // Owner of the media set
            $table->foreignUuid('project_uuid')->nullable()->constrained('memora_projects', 'uuid')->cascadeOnDelete(); // Project this media set belongs to (optional)
            $table->foreignUuid('selection_uuid')->nullable()->constrained('memora_selections', 'uuid')->cascadeOnDelete(); // Selection this media set belongs to (optional)
            $table->foreignUuid('proof_uuid')->nullable()->constrained('memora_proofing', 'uuid')->cascadeOnDelete(); // Proofing phase this media set belongs to (optional)
            $table->foreignUuid('collection_uuid')->nullable()->constrained('memora_collections', 'uuid')->cascadeOnDelete(); // Collection this media set belongs to (optional)

            // Basic information
            $table->string('name'); // Name of the media set
            $table->text('description')->nullable(); // Description of the media set
            $table->json('metadata')->nullable(); // Additional metadata stored as JSON

            // Ordering and limits
            $table->integer('order')->default(0); // Display order of the media set
            $table->integer('selection_limit')->nullable(); // Maximum number of items that can be selected from this set

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
        Schema::dropIfExists('memora_media_sets');
    }
};
