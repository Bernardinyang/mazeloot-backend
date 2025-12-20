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
        Schema::create('user_starred_selections', static function (Blueprint $table) {
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('selection_uuid')->constrained('memora_selections', 'uuid')->cascadeOnDelete();
            $table->timestamps();

            // Composite primary key
            $table->primary(['user_uuid', 'selection_uuid']);
            
            // Index for faster lookups
            $table->index('user_uuid');
            $table->index('selection_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_starred_selections');
    }
};
