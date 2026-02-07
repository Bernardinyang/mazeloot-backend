<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_upgrade_requests', static function (Blueprint $table) {
            $table->text('checkout_url')->nullable()->after('checkout_session_id');
        });
        Schema::table('memora_downgrade_requests', static function (Blueprint $table) {
            $table->text('checkout_url')->nullable()->after('checkout_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('memora_upgrade_requests', static function (Blueprint $table) {
            $table->dropColumn('checkout_url');
        });
        Schema::table('memora_downgrade_requests', static function (Blueprint $table) {
            $table->dropColumn('checkout_url');
        });
    }
};
