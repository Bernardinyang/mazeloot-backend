<?php

use App\Domains\Memora\Enums\BorderStyle;
use App\Domains\Memora\Enums\FontStyle;
use App\Domains\Memora\Enums\TextTransform;
use App\Domains\Memora\Enums\WatermarkPosition;
use App\Domains\Memora\Enums\WatermarkType;
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
        Schema::create('memora_watermarks', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', WatermarkType::values());

            // Image type fields
            $table->string('image_url')->nullable();

            // Text type fields
            $table->text('text')->nullable();
            $table->string('font_family')->nullable();
            $table->enum('font_style', FontStyle::values())->nullable();
            $table->string('font_color', 7)->nullable(); // Hex color
            $table->string('background_color', 7)->nullable(); // Hex color
            $table->decimal('line_height', 3, 2)->nullable();
            $table->decimal('letter_spacing', 5, 2)->nullable();
            $table->integer('padding')->nullable();
            $table->enum('text_transform', TextTransform::values())->nullable();
            $table->integer('border_radius')->nullable();
            $table->integer('border_width')->nullable();
            $table->string('border_color', 7)->nullable(); // Hex color
            $table->enum('border_style', BorderStyle::values())->nullable();

            // Common fields
            $table->integer('scale')->default(50); // Percentage 0-100
            $table->integer('opacity')->default(80); // Percentage 0-100
            $table->enum('position', WatermarkPosition::values())->default(WatermarkPosition::BOTTOM_RIGHT->value);

            $table->timestamps();

            // Indexes
            $table->index('user_uuid');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_watermarks');
    }
};
