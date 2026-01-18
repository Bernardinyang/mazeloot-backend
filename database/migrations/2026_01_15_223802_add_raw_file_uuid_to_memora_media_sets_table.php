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
        Schema::table('memora_media_sets', function (Blueprint $table) {
            $table->foreignUuid('raw_file_uuid')->nullable()->after('selection_uuid')->constrained('memora_raw_files', 'uuid')->cascadeOnDelete();
            $table->integer('raw_file_limit')->nullable()->after('selection_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_media_sets', function (Blueprint $table) {
            $table->dropForeign(['raw_file_uuid']);
            $table->dropColumn(['raw_file_uuid', 'raw_file_limit']);
        });
    }
};
