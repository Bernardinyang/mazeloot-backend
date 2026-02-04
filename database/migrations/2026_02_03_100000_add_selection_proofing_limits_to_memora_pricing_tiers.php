<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_pricing_tiers', function (Blueprint $table) {
            $table->unsignedInteger('selection_limit')->nullable()->after('collection_limit');
            $table->unsignedInteger('proofing_limit')->nullable()->after('selection_limit');
        });
    }

    public function down(): void
    {
        Schema::table('memora_pricing_tiers', function (Blueprint $table) {
            $table->dropColumn(['selection_limit', 'proofing_limit']);
        });
    }
};
