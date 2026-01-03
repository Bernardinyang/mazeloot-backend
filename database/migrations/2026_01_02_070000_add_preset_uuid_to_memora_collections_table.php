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
        Schema::table('memora_collections', function (Blueprint $table) {
            $table->foreignUuid('preset_uuid')->nullable()->after('project_uuid')->constrained('memora_presets', 'uuid')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_collections', function (Blueprint $table) {
            $table->dropForeign(['preset_uuid']);
            $table->dropColumn('preset_uuid');
        });
    }
};
