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
        Schema::create('memora_presets', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('default_watermark_uuid')->nullable()->constrained('memora_watermarks', 'uuid')->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->integer('order')->default(0);
            $table->text('collection_tags')->nullable(); // Comma-separated tags
            $table->json('photo_sets')->nullable(); // Array of photo set names
            $table->boolean('email_registration')->default(false);
            $table->boolean('gallery_assist')->default(false);
            $table->boolean('slideshow')->default(true);
            $table->string('slideshow_speed')->default('regular');
            $table->boolean('slideshow_auto_loop')->default(true);
            $table->boolean('social_sharing')->default(true);
            $table->string('language', 10)->default('en');

            // Design section fields (excluding cover style/focal point - removed from presets)
            $table->string('design_font_family')->default('sans');
            $table->string('design_font_style')->default('normal');
            $table->string('design_color_palette')->default('light');
            $table->string('design_grid_style')->default('grid');
            $table->integer('design_grid_columns')->default(3);
            $table->string('design_thumbnail_orientation')->default('square');
            $table->integer('design_grid_spacing')->default(16);
            $table->string('design_tab_style')->default('icon-text');

            // Privacy section fields
            $table->boolean('privacy_collection_password')->default(false);
            $table->boolean('privacy_show_on_homepage')->default(true);
            $table->boolean('privacy_client_exclusive_access')->default(false);
            $table->boolean('privacy_allow_clients_mark_private')->default(false);
            $table->json('privacy_client_only_sets')->nullable(); // Array of set IDs

            // Download section fields
            $table->boolean('download_photo_download')->default(true);
            $table->boolean('download_high_resolution_enabled')->default(true);
            $table->string('download_high_resolution_size')->default('3600px');
            $table->boolean('download_web_size_enabled')->default(true);
            $table->string('download_web_size')->default('1024px');
            $table->boolean('download_video_download')->default(false);
            $table->boolean('download_download_pin')->default(false);
            $table->boolean('download_download_pin_enabled')->default(false);
            $table->boolean('download_limit_downloads')->default(false);
            $table->integer('download_download_limit')->default(1);
            $table->boolean('download_restrict_to_contacts')->default(false);
            $table->json('download_downloadable_sets')->nullable(); // Array of set IDs

            // Favorite section fields
            $table->boolean('favorite_favorite_enabled')->default(true);
            $table->boolean('favorite_favorite_photos')->default(true);
            $table->boolean('favorite_favorite_notes')->default(true);

            $table->timestamps();

            // Indexes
            $table->index('user_uuid');
            $table->index('is_selected');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_presets');
    }
};
