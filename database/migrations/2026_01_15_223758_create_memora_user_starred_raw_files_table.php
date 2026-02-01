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
        Schema::create('user_starred_raw_files', static function (Blueprint $table) {
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('raw_file_uuid')->constrained('memora_raw_files', 'uuid')->cascadeOnDelete();
            $table->timestamps();

            // Composite primary key
            $table->primary(['user_uuid', 'raw_file_uuid']);

            // Index for faster lookups
            $table->index('user_uuid');
            $table->index('raw_file_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_starred_raw_files');
    }
};
