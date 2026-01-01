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
        Schema::create('memora_social_links', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('platform_uuid')->constrained('social_media_platforms', 'uuid')->cascadeOnDelete();
            $table->string('url');
            $table->boolean('is_active')->default(true);
            $table->integer('order')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('user_uuid');
            $table->index('platform_uuid');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_social_links');
    }
};
