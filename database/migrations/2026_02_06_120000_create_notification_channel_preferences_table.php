<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channel_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('product', 64)->default('memora');
            $table->boolean('notify_email')->default(true);
            $table->boolean('notify_in_app')->default(true);
            $table->boolean('notify_whatsapp')->default(false);
            $table->string('whatsapp_number', 24)->nullable();
            $table->timestamps();

            $table->unique(['user_uuid', 'product']);
            $table->index('user_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_preferences');
    }
};
