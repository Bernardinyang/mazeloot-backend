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
        Schema::table('memora_presets', function (Blueprint $table) {
            $table->string('slideshow_speed')->default('regular')->after('slideshow');
            $table->boolean('slideshow_auto_loop')->default(true)->after('slideshow_speed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_presets', function (Blueprint $table) {
            $table->dropColumn(['slideshow_speed', 'slideshow_auto_loop']);
        });
    }
};
