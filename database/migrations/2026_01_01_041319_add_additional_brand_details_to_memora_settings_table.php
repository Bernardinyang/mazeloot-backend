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
            $table->string('branding_tagline')->nullable()->after('branding_location');
            $table->text('branding_description')->nullable()->after('branding_tagline');
            $table->string('branding_address_street')->nullable()->after('branding_description');
            $table->string('branding_address_city')->nullable()->after('branding_address_street');
            $table->string('branding_address_state')->nullable()->after('branding_address_city');
            $table->string('branding_address_zip')->nullable()->after('branding_address_state');
            $table->string('branding_address_country')->nullable()->after('branding_address_zip');
            $table->text('branding_business_hours')->nullable()->after('branding_address_country');
            $table->string('branding_contact_name')->nullable()->after('branding_business_hours');
            $table->string('branding_tax_vat_id')->nullable()->after('branding_contact_name');
            $table->integer('branding_founded_year')->nullable()->after('branding_tax_vat_id');
            $table->string('branding_industry')->nullable()->after('branding_founded_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memora_settings', function (Blueprint $table) {
            $table->dropColumn([
                'branding_tagline',
                'branding_description',
                'branding_address_street',
                'branding_address_city',
                'branding_address_state',
                'branding_address_zip',
                'branding_address_country',
                'branding_business_hours',
                'branding_contact_name',
                'branding_tax_vat_id',
                'branding_founded_year',
                'branding_industry',
            ]);
        });
    }
};
