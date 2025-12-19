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
        Schema::create('memora_cover_styles', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));

            $table->string('name'); // Display name like "Modern", "Joy", "Split Layout"
            $table->string('slug')->unique(); // URL-friendly identifier like "modern", "joy", "split-layout"
            $table->text('description')->nullable(); // Description of the cover style
            $table->boolean('is_active')->default(true); // Whether this style is available for selection
            $table->boolean('is_default')->default(false); // Whether this is the default style
            $table->json('config')->nullable(); // Full configuration object matching frontend coverStyleConfigs structure
            $table->string('preview_image_url')->nullable(); // Preview image for admin panel
            $table->integer('order')->default(0); // Display order for admin panel

            $table->timestamps();

            // Add indexes
            $table->index('is_active');
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_cover_styles');
    }
};

