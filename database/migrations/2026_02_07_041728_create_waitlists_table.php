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
        Schema::create('waitlists', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->string('name');
            $table->string('email')->index();
            $table->uuid('product_uuid')->nullable()->constrained('products', 'uuid')->nullOnDelete();
            $table->enum('status', ['not_registered', 'registered'])->default('not_registered')->index();
            $table->uuid('user_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['product_uuid', 'status']);
            $table->unique(['email', 'product_uuid'], 'waitlists_email_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
