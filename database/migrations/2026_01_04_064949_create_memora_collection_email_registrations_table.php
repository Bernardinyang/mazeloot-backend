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
        Schema::create('memora_collection_email_registrations', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()->default(DB::raw('(UUID())'));
            $table->foreignUuid('collection_uuid')->constrained('memora_collections', 'uuid')->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->foreignUuid('user_uuid')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_access_at')->nullable();
            $table->timestamps();

            $table->index(['collection_uuid', 'email'], 'mcer_collection_email_idx');
            $table->index(['collection_uuid', 'user_uuid'], 'mcer_collection_user_idx');
            $table->index('created_at', 'mcer_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_collection_email_registrations');
    }
};
