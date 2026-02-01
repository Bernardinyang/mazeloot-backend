<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memora_byo_addons', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('label');
            $table->enum('type', ['checkbox', 'storage']);
            $table->unsignedInteger('price_monthly_cents');
            $table->unsignedInteger('price_annual_cents');
            $table->unsignedBigInteger('storage_bytes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('memora_byo_config', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('base_price_monthly_cents');
            $table->unsignedInteger('base_price_annual_cents');
            $table->unsignedBigInteger('base_storage_bytes');
            $table->unsignedInteger('base_project_limit');
            $table->unsignedInteger('annual_discount_months')->default(2);
            $table->timestamps();
        });

        Schema::create('memora_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('price_monthly_cents')->default(0);
            $table->unsignedInteger('price_annual_cents')->default(0);
            $table->unsignedBigInteger('storage_bytes')->nullable();
            $table->unsignedInteger('project_limit')->nullable();
            $table->unsignedInteger('collection_limit')->nullable();
            $table->unsignedInteger('max_revisions')->default(0);
            $table->unsignedInteger('watermark_limit')->nullable();
            $table->unsignedInteger('preset_limit')->nullable();
            $table->unsignedInteger('team_seats')->default(1);
            $table->unsignedInteger('raw_file_limit')->nullable();
            $table->json('features')->nullable();
            $table->json('features_display')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memora_pricing_tiers');
        Schema::dropIfExists('memora_byo_config');
        Schema::dropIfExists('memora_byo_addons');
    }
};
