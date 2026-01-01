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
        Schema::create('memora_email_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('notification_type');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            // Composite unique constraint on user_uuid + notification_type
            $table->unique(['user_uuid', 'notification_type'], 'user_notification_type_unique');

            // Indexes
            $table->index('user_uuid');
            $table->index('notification_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_email_notifications');
    }
};
