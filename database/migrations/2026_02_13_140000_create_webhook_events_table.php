<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->string('event_type', 128)->nullable()->index();
            $table->string('event_id', 255)->nullable()->index();
            $table->string('status', 32)->index(); // received, processed, failed
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
