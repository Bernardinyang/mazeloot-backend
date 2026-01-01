<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('memora_settings', function (Blueprint $table) {
            $table->string('branding_name')->nullable()->after('branding_show_mazeloot_branding');
            $table->string('branding_support_email')->nullable()->after('branding_name');
            $table->string('branding_support_phone')->nullable()->after('branding_support_email');
            $table->string('branding_website')->nullable()->after('branding_support_phone');
            $table->string('branding_location')->nullable()->after('branding_website');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_settings', function (Blueprint $table) {
            $table->dropColumn([
                'branding_name',
                'branding_support_email',
                'branding_support_phone',
                'branding_website',
                'branding_location',
            ]);
        });
    }
};
