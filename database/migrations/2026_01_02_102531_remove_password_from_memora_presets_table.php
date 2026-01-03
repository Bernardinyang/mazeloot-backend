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
        // Convert existing string values to boolean (non-empty strings = true, empty/null = false)
        DB::statement('UPDATE memora_presets SET privacy_collection_password = CASE 
            WHEN privacy_collection_password IS NOT NULL AND privacy_collection_password != "" THEN 1 
            ELSE 0 
        END');

        Schema::table('memora_presets', function (Blueprint $table) {
            // Change password field from string to boolean (presets are templates, not actual collections)
            $table->boolean('privacy_collection_password')->default(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_presets', function (Blueprint $table) {
            $table->string('privacy_collection_password')->nullable()->change();
        });

        // Convert boolean back to string (true = '1', false = null)
        DB::statement('UPDATE memora_presets SET privacy_collection_password = CASE 
            WHEN privacy_collection_password = 1 THEN "1" 
            ELSE NULL 
        END');
    }
};
