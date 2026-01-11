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
        Schema::create('notifications', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->enum('product', ['memora', 'profolio', 'general'])->default('general');
            $table->string('type'); // e.g., 'collection_created', 'watermark_updated', etc.
            $table->string('title');
            $table->text('message');
            $table->text('description')->nullable();
            $table->string('action_url')->nullable(); // Optional navigation path
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Indexes for better query performance
            $table->index(['user_uuid', 'created_at']);
            $table->index(['user_uuid', 'product', 'read_at']);
            $table->index(['product', 'created_at']);
            $table->index('read_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
