<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->unsignedInteger('base_cost_monthly_cents')->nullable()->after('base_price_annual_cents');
            $table->unsignedInteger('base_cost_annual_cents')->nullable()->after('base_cost_monthly_cents');
        });

        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->unsignedInteger('cost_monthly_cents')->nullable()->after('price_annual_cents');
            $table->unsignedInteger('cost_annual_cents')->nullable()->after('cost_monthly_cents');
        });
    }

    public function down(): void
    {
        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->dropColumn(['base_cost_monthly_cents', 'base_cost_annual_cents']);
        });
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->dropColumn(['cost_monthly_cents', 'cost_annual_cents']);
        });
    }
};
