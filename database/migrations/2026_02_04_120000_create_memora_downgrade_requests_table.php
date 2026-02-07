<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memora_downgrade_requests', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('current_tier', 50);
            $table->string('target_tier', 50)->default('starter');
            $table->string('status', 20)->default('pending');
            $table->string('confirm_token', 64)->nullable()->unique();
            $table->timestamp('confirm_token_expires_at')->nullable();
            $table->string('checkout_session_id', 255)->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memora_downgrade_requests');
    }
};
