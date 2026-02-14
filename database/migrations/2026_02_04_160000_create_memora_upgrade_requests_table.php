<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memora_upgrade_requests', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('current_tier', 50)->nullable();
            $table->string('target_tier', 50);
            $table->string('status', 20)->default('pending');
            $table->string('checkout_session_id', 255)->nullable();
            $table->text('checkout_url')->nullable();
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
        Schema::dropIfExists('memora_upgrade_requests');
    }
};
