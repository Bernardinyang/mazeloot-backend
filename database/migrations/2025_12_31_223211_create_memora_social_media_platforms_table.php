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
        Schema::create('social_media_platforms', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('base_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('slug');
            $table->index('is_active');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_platforms');
    }
};
