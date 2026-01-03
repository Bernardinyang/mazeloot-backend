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
        Schema::table('memora_media_sets', function (Blueprint $table) {
            $table->foreignUuid('collection_uuid')->nullable()->after('proof_uuid')->constrained('memora_collections', 'uuid')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_media_sets', function (Blueprint $table) {
            $table->dropForeign(['collection_uuid']);
            $table->dropColumn('collection_uuid');
        });
    }
};
