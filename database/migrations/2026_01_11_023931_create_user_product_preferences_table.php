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
        Schema::create('user_product_preferences', static function (Blueprint $table) {
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('product_uuid')->constrained('products', 'uuid')->cascadeOnDelete();
            $table->string('domain')->nullable()->unique(); // Globally unique domain
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamps();

            // Unique constraint to prevent duplicate product selections per user
            $table->unique(['user_uuid', 'product_uuid']);
            
            // Indexes
            $table->index('user_uuid');
            $table->index('product_uuid');
            $table->index('domain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_product_preferences');
    }
};
