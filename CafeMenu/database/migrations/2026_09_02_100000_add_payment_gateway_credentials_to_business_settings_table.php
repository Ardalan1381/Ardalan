<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // Encrypted at rest (see BusinessSetting::casts) — this holds
            // merchant IDs and, for Mellat, a bank terminal username/password.
            $table->text('payment_gateway_credentials')->nullable()->after('enabled_online_gateways');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('payment_gateway_credentials');
        });
    }
};
