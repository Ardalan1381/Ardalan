<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->unsignedTinyInteger('currency_minor_units')->default(2);
            $table->string('locale', 10)->default('en');
            $table->string('timezone')->default('UTC');
            $table->boolean('tax_enabled')->default(false);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->boolean('service_charge_enabled')->default(false);
            $table->unsignedInteger('service_charge_rate_bps')->default(0);
            $table->json('working_hours')->nullable();
            $table->string('theme_color_primary', 7)->default('#111827');
            $table->string('theme_color_secondary', 7)->default('#6366F1');
            $table->json('social_links')->nullable();
            $table->boolean('accepts_dine_in')->default(true);
            $table->boolean('accepts_takeaway')->default(true);
            $table->boolean('accepts_pickup')->default(true);
            $table->boolean('accepts_delivery')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
