<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_starred_media', function (Blueprint $table) {
            $table->uuid('user_uuid');
            $table->uuid('media_uuid');
            $table->timestamps();

            $table->primary(['user_uuid', 'media_uuid']);
            $table->foreign('user_uuid')
                ->references('uuid')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('media_uuid')
                ->references('uuid')
                ->on('memora_media')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_starred_media');
    }
};

