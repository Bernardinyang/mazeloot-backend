<?php

use App\Domains\Memora\Enums\SelectionStatusEnum;
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
        Schema::create('memora_raw_files', static function (Blueprint $table) {
            $table->unsignedBigInteger('id')->nullable();
            $table->uuid('uuid')->primary()->default(DB::raw('(UUID())'));
            $table->foreignUuid('user_uuid')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('folder_uuid')->nullable()->constrained('memora_folders', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('project_uuid')->nullable()->constrained('memora_projects', 'uuid')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', SelectionStatusEnum::values())->default(SelectionStatusEnum::DRAFT->value);
            $table->string('color', 7)->default('#3B82F6'); // Default blue color
            $table->string('cover_photo_url')->nullable();
            $table->json('cover_focal_point')->nullable();
            $table->string('password')->nullable();
            $table->json('allowed_emails')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memora_raw_files');
    }
};
