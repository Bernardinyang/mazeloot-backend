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
        Schema::create('memora_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->unique()->constrained('users', 'uuid')->cascadeOnDelete();

            // Branding fields
            $table->string('branding_domain')->nullable();
            $table->string('branding_custom_domain')->nullable();
            $table->foreignUuid('branding_logo_uuid')->nullable()->constrained('user_files', 'uuid')->nullOnDelete();
            $table->foreignUuid('branding_favicon_uuid')->nullable()->constrained('user_files', 'uuid')->nullOnDelete();
            $table->boolean('branding_show_mazeloot_branding')->default(true);

            // Preference fields
            $table->enum('preference_filename_display', ['show', 'hide'])->default('show');
            $table->enum('preference_search_engine_visibility', ['homepage-only', 'all', 'none'])->default('homepage-only');
            $table->enum('preference_sharpening_level', ['optimal', 'low', 'medium', 'high'])->default('optimal');
            $table->boolean('preference_raw_photo_support')->default(false);
            $table->text('preference_terms_of_service')->nullable();
            $table->text('preference_privacy_policy')->nullable();
            $table->boolean('preference_enable_cookie_banner')->default(false);
            $table->string('preference_language', 10)->default('en');
            $table->string('preference_timezone')->default('UTC');

            // Homepage fields
            $table->boolean('homepage_status')->default(true);
            $table->string('homepage_password')->nullable();
            $table->string('homepage_biography', 200)->nullable();
            $table->json('homepage_info')->nullable();
            $table->string('homepage_collection_sort_order')->nullable();

            // Email settings
            $table->string('email_from_name')->nullable();
            $table->string('email_from_address')->nullable();
            $table->string('email_reply_to')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_settings');
    }
};
