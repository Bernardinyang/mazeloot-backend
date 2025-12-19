<?php

use App\Enums\ProjectStatus;
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
            $table->id();
            $table->uuid()->unique()
                ->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->uuid('preset_uuid')->nullable(); // No FK - table doesn't exist yet
            $table->uuid('watermark_uuid')->nullable(); // No FK - table doesn't exist yet, fixed typo from watermark_uid
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->enum('status', ProjectStatus::values())->default(ProjectStatus::DRAFT->value);
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
