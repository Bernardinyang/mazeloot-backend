<?php

use App\Domains\Memora\Enums\ProjectStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memora_projects', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreign('preset_uuid')->references('uuid')->on('memora_presets')->nullOnDelete();
            $table->foreign('watermark_uuid')->references('uuid')->on('memora_watermarks')->nullOnDelete();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ProjectStatusEnum::values())->default(ProjectStatusEnum::DRAFT->value);
            $table->string('color', 7)->default('#3B82F6'); // Default blue color
            $table->boolean('has_selections')->default(false);
            $table->boolean('has_proofing')->default(false);
            $table->boolean('has_collections')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_projects');
    }
};
