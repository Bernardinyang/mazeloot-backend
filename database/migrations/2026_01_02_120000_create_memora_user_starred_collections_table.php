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
        Schema::create('memora_user_starred_collections', static function (Blueprint $table) {
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('collection_uuid')->constrained('memora_collections', 'uuid')->cascadeOnDelete();
            $table->timestamps();

            // Composite primary key
            $table->primary(['user_uuid', 'collection_uuid']);

            // Index for faster lookups
            $table->index('user_uuid');
            $table->index('collection_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_user_starred_collections');
    }
};
