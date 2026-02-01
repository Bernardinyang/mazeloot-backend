<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memora_subscriptions', function (Blueprint $table) {
            $table->string('payment_provider', 20)->nullable()->default('stripe')->after('user_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('memora_subscriptions', function (Blueprint $table) {
            $table->dropColumn('payment_provider');
        });
    }
};
