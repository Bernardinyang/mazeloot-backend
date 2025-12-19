<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memora_media_sets', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('project_uuid')->nullable()->constrained('memora_projects', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('selection_uuid')->nullable()->constrained('memora_selections', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('proof_uuid')->nullable()->constrained('memora_proofing', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_media_sets');
    }
};
