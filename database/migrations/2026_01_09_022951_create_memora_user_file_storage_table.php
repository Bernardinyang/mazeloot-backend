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
        Schema::create('memora_user_file_storage', static function (Blueprint $table) {
            $table->uuid('user_uuid')->primary();
            $table->foreign('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('total_storage_bytes')->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_user_file_storage');
    }
};
