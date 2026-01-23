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
        Schema::table('early_access_requests', function (Blueprint $table) {
            // Composite index for checking user's request status (e.g., pending requests)
            $table->index(['user_uuid', 'status'], 'early_access_requests_user_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('early_access_requests', function (Blueprint $table) {
            $table->dropIndex('early_access_requests_user_status_index');
        });
    }
};
