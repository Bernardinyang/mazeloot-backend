<?php

use App\Domains\Memora\Enums\MediaTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memora_media', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('media_set_uuid')->constrained('memora_media_sets', 'uuid')->cascadeOnDelete();

            $table->boolean('is_selected')->default(false);
            $table->timestamp('selected_at')->nullable();

            $table->integer('revision_number')->nullable();

            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();

            $table->uuid('original_media_uuid')->nullable();
            $table->string('url'); // From upload system
            $table->string('thumbnail_url')->nullable();
            $table->string('low_res_copy_url')->nullable();
            $table->enum('type', MediaTypeEnum::values());
            $table->string('filename');
            $table->string('mime_type');
            $table->integer('size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
        
        // Add unique index and self-referencing FK after table creation
        Schema::table('memora_media', static function (Blueprint $table) {
            $table->unique('uuid');
            $table->foreign('original_media_uuid')->references('uuid')->on('memora_media')->nullOnDelete();
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
