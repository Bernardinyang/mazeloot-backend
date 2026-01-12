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
        Schema::table('memora_media_sets', static function (Blueprint $table) {
            $table->foreignUuid('raw_files_uuid')->nullable()->after('collection_uuid')->constrained('memora_raw_files', 'uuid')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_media_sets', static function (Blueprint $table) {
            $table->dropForeign(['raw_files_uuid']);
            $table->dropColumn('raw_files_uuid');
        });
    }
};
