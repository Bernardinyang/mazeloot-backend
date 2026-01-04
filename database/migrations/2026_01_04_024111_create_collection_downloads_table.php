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
        Schema::create('memora_collection_downloads', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->default(DB::raw('(UUID())'));
            $table->foreignUuid('collection_uuid')->constrained('memora_collections', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('media_uuid')->nullable()->constrained('memora_media', 'uuid')->nullOnDelete();
            $table->string('email')->nullable();
            $table->foreignUuid('user_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->string('download_type')->nullable(); // 'full', 'web', 'original', 'thumbnail'
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['collection_uuid', 'email']);
            $table->index(['collection_uuid', 'user_uuid']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_collection_downloads');
    }
};
