<?php

use App\Domains\Memora\Enums\MediaFeedbackType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('memora_media_feedback', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()
                ->default(DB::raw('(UUID())'));
            $table->foreignUuid('media_uuid')->constrained('memora_media', 'uuid')->cascadeOnDelete();
            $table->enum('type', MediaFeedbackType::values());
            $table->text('content'); // Text content or URL for video/audio
            $table->json('created_by')->nullable(); // client-identifier
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memora_media_feedback');
    }
};
