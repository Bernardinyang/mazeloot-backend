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
            // Drop foreign key constraint first
            $table->dropForeign(['design_cover_uuid']);

            // Drop the columns
            $table->dropColumn(['design_cover_uuid', 'design_cover_focal_point']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_presets', function (Blueprint $table) {
            // Re-add the columns
            $table->foreignUuid('design_cover_uuid')->nullable()->after('language')->constrained('memora_cover_styles', 'uuid')->nullOnDelete();
            $table->json('design_cover_focal_point')->nullable()->after('design_cover_uuid');
        });
    }
};
