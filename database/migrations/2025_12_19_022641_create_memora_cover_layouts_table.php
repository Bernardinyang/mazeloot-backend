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
        Schema::create('memora_cover_layouts', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));

            $table->string('name'); // Display name like "Hero Stack", "Centered Card"
            $table->string('slug')->unique(); // URL-friendly identifier
            $table->text('description')->nullable(); // Description of the layout
            $table->json('layout_config')->nullable(); // Layout intent JSON configuration
            $table->boolean('is_active')->default(true); // Whether this layout is available
            $table->boolean('is_default')->default(false); // Whether this is the default layout
            $table->integer('order')->default(0); // Display order

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
        Schema::dropIfExists('memora_cover_layouts');
    }
};
