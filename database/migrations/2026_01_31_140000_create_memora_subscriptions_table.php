<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memora_subscriptions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid');
            $table->string('payment_provider', 20)->nullable()->default('stripe');
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_customer_id');
            $table->string('stripe_price_id');
            $table->string('tier'); // starter, pro, studio, business, byo
            $table->string('billing_cycle'); // monthly, annual
            $table->string('status'); // active, canceled, past_due, incomplete, trialing
            $table->integer('amount'); // in cents
            $table->string('currency', 3)->default('usd');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('metadata')->nullable(); // BYO addons, etc.
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->onDelete('cascade');
            $table->index(['user_uuid', 'status']);
            $table->index('stripe_customer_id');
        });

        // Store Stripe customer IDs on users
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('memora_tier');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });
        Schema::dropIfExists('memora_subscriptions');
    }
};
