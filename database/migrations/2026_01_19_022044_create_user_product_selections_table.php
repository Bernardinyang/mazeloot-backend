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
        Schema::create('user_product_selections', static function (Blueprint $table) {
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->timestamp('selected_at')->useCurrent();
            $table->timestamps();

            $table->primary(['user_uuid', 'product_uuid']);
            $table->index('user_uuid');
            $table->index('product_uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_product_selections');
    }
};
