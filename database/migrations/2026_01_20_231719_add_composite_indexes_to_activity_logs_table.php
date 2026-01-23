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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Composite index for common query: user + action + date
            $table->index(['user_uuid', 'action', 'created_at'], 'activity_logs_user_action_created_idx');
            
            // Composite index for common query: subject type + action + date
            $table->index(['subject_type', 'action', 'created_at'], 'activity_logs_subject_action_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_user_action_created_idx');
            $table->dropIndex('activity_logs_subject_action_created_idx');
        });
    }
};
