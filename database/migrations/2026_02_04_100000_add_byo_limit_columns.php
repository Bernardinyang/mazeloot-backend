<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->unsignedInteger('selection_limit_granted')->nullable()->after('storage_bytes');
            $table->unsignedInteger('proofing_limit_granted')->nullable()->after('selection_limit_granted');
            $table->unsignedInteger('collection_limit_granted')->nullable()->after('proofing_limit_granted');
            $table->unsignedInteger('project_limit_granted')->nullable()->after('collection_limit_granted');
        });

        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->unsignedInteger('base_selection_limit')->default(0)->after('base_project_limit');
            $table->unsignedInteger('base_proofing_limit')->default(0)->after('base_selection_limit');
            $table->unsignedInteger('base_collection_limit')->default(0)->after('base_proofing_limit');
        });
    }

    public function down(): void
    {
        Schema::table('memora_byo_addons', function (Blueprint $table) {
            $table->dropColumn([
                'selection_limit_granted',
                'proofing_limit_granted',
                'collection_limit_granted',
                'project_limit_granted',
            ]);
        });

        Schema::table('memora_byo_config', function (Blueprint $table) {
            $table->dropColumn([
                'base_selection_limit',
                'base_proofing_limit',
                'base_collection_limit',
            ]);
        });
    }
};
