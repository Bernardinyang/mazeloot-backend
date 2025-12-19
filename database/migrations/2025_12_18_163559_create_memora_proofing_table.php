<?php

use App\Enums\ProofingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memora_proofing', static function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique()
                ->default(DB::raw('(UUID())'));
            $table->foreignUuid('folder_uuid')->nullable()->constrained('memora_folders', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('project_uuid')->nullable()->constrained('memora_projects')->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ProofingStatus::values())->default(ProofingStatus::DRAFT->value);
            $table->string('color', 7)->default('#F59E0B'); // Default amber/orange color
            $table->integer('max_revisions')->default(5);
            $table->integer('current_revision')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_proofing');
    }
};
