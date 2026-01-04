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
            $table->boolean('is_private')->default(false)->after('is_rejected');
            $table->timestamp('marked_private_at')->nullable()->after('is_private');
            $table->string('marked_private_by_email')->nullable()->after('marked_private_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_media', function (Blueprint $table) {
            $table->dropColumn(['is_private', 'marked_private_at', 'marked_private_by_email']);
        });
    }
};

