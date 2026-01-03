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
        // Rename design_navigation_style to design_tab_style if it exists
        if (Schema::hasColumn('memora_presets', 'design_navigation_style')) {
            DB::statement('ALTER TABLE memora_presets CHANGE COLUMN design_navigation_style design_tab_style VARCHAR(255) DEFAULT "icon-text"');
        }
        
        // Rename design_thumbnail_size to design_thumbnail_orientation if it exists
        if (Schema::hasColumn('memora_presets', 'design_thumbnail_size')) {
            DB::statement('ALTER TABLE memora_presets CHANGE COLUMN design_thumbnail_size design_thumbnail_orientation VARCHAR(255) DEFAULT "medium"');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename back if columns exist
        if (Schema::hasColumn('memora_presets', 'design_tab_style')) {
            DB::statement('ALTER TABLE memora_presets CHANGE COLUMN design_tab_style design_navigation_style VARCHAR(255) DEFAULT "icon-text"');
        }
        
        if (Schema::hasColumn('memora_presets', 'design_thumbnail_orientation')) {
            DB::statement('ALTER TABLE memora_presets CHANGE COLUMN design_thumbnail_orientation design_thumbnail_size VARCHAR(255) DEFAULT "medium"');
        }
    }
};
