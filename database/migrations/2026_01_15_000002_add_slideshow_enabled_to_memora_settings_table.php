<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memora_settings', function (Blueprint $table) {
            $table->boolean('homepage_slideshow_enabled')->default(false)->after('homepage_collection_sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_settings', function (Blueprint $table) {
            $table->dropColumn('homepage_slideshow_enabled');
        });
    }
};

