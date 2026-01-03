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
        Schema::table('memora_collections', function (Blueprint $table) {
            $table->foreignUuid('watermark_uuid')->nullable()->after('preset_uuid')->constrained('memora_watermarks', 'uuid')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_collections', function (Blueprint $table) {
            $table->dropForeign(['watermark_uuid']);
            $table->dropColumn('watermark_uuid');
        });
    }
};
