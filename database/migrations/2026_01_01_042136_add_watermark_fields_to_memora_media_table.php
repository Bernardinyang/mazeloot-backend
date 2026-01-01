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
        Schema::table('memora_media', function (Blueprint $table) {
            $table->foreignUuid('watermark_uuid')->nullable()->after('user_file_uuid')->constrained('memora_watermarks', 'uuid')->nullOnDelete();
            $table->foreignUuid('original_file_uuid')->nullable()->after('watermark_uuid')->constrained('user_files', 'uuid')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_media', function (Blueprint $table) {
            $table->dropForeign(['watermark_uuid']);
            $table->dropForeign(['original_file_uuid']);
            $table->dropColumn(['watermark_uuid', 'original_file_uuid']);
        });
    }
};
