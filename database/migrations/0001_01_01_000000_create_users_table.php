<?php

use App\Enums\UserRoleEnum;
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
        Schema::create('users', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('status_uuid')->nullable()->constrained('user_statuses', 'uuid')->nullOnDelete();
            $table->enum('role', UserRoleEnum::values())->default(UserRoleEnum::USER->value);
            $table->string('memora_tier', 32)->default('starter');
            $table->string('referral_code', 32)->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->unique();
            $table->string('mobile_number')->nullable();
            $table->string('username')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->integer('age')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('profile_photo')->nullable();
            $table->string('cover_photo')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable for OAuth users
            $table->string('provider')->nullable(); // e.g., 'google', 'github', etc.
            $table->string('provider_id')->nullable(); // OAuth provider's user ID
            $table->json('metadata')->nullable();

            // Index for OAuth lookups
            $table->index(['provider', 'provider_id']);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('referred_by_user_uuid')->nullable()->after('referral_code')->constrained('users', 'uuid')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('code', 6);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_uuid', 'code']);
            $table->index('expires_at');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_uuid')->nullable()->index()->constrained('users', 'uuid')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('referral_invites', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('referrer_user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('email');
            $table->timestamp('sent_at');
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_user_uuid', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_invites');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
