<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_pricing_tiers', function (Blueprint $table) {
            $table->unsignedInteger('set_limit_per_phase')->nullable()->after('preset_limit');
            $table->json('capabilities')->nullable()->after('features_display');
        });
    }

    public function down(): void
    {
        Schema::table('memora_pricing_tiers', function (Blueprint $table) {
            $table->dropColumn(['set_limit_per_phase', 'capabilities']);
        });
    }
};
