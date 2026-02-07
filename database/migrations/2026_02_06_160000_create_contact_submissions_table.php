<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', static function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('company', 200)->nullable();
            $table->string('email');
            $table->string('country', 10)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
