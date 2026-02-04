<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->unsignedInteger('raw_file_limit_granted')->nullable()->after('project_limit_granted');
        });

        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->unsignedInteger('base_raw_file_limit')->default(0)->after('base_collection_limit');
        });
    }

    public function down(): void
    {
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->dropColumn('raw_file_limit_granted');
        });

        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->dropColumn('base_raw_file_limit');
        });
    }
};
