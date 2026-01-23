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
            $table->unique('branding_domain', 'memora_settings_branding_domain_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_settings', function (Blueprint $table) {
            $table->dropUnique('memora_settings_branding_domain_unique');
        });
    }
};
