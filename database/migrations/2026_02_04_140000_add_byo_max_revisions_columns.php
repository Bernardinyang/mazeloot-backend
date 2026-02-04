<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->unsignedInteger('max_revisions_granted')->nullable()->after('raw_file_limit_granted');
        });

        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->unsignedInteger('base_max_revisions')->default(0)->after('base_raw_file_limit');
        });
    }

    public function down(): void
    {
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->dropColumn('max_revisions_granted');
        });

        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->dropColumn('base_max_revisions');
        });
    }
};
