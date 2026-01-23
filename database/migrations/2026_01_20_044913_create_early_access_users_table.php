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
        Schema::create('early_access_users', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->unique()->constrained('users', 'uuid')->cascadeOnDelete();

            // Discount rewards
            $table->integer('discount_percentage')->default(0)->comment('Percentage discount (0-100)');
            $table->json('discount_rules')->nullable()->comment('Product-specific discount rules');

            // Feature flags
            $table->json('feature_flags')->nullable()->comment('Array of enabled feature flags');

            // Storage boost
            $table->decimal('storage_multiplier', 5, 2)->default(1.0)->comment('Storage multiplier (e.g., 1.5 = 150% storage)');

            // Priority support
            $table->boolean('priority_support')->default(false);

            // Exclusive badge
            $table->boolean('exclusive_badge')->default(true);

            // Trial extension
            $table->integer('trial_extension_days')->default(0)->comment('Additional trial days');

            // Custom branding
            $table->boolean('custom_branding_enabled')->default(false);

            // Release version
            $table->string('release_version')->nullable()->comment('Current release version for user');

            // Timestamps
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('early_access_users');
    }
};
