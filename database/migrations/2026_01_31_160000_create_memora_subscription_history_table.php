<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memora_subscription_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_uuid');
            $table->string('event_type', 50); // created, upgraded, downgraded, cancelled, renewed, payment_failed, reactivated
            $table->string('from_tier', 32)->nullable();
            $table->string('to_tier', 32)->nullable();
            $table->string('billing_cycle', 20)->nullable(); // monthly, annual
            $table->unsignedBigInteger('amount_cents')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('payment_provider', 20)->nullable(); // stripe, paystack, flutterwave
            $table->string('payment_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->index(['user_uuid', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memora_subscription_history');
    }
};
