<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // A separate master switch from accepts_dine_in/takeaway/pickup/
            // delivery: those choose which order TYPES are offered, this
            // decides whether the public customer can place an order at
            // all. When off, the public menu is browse-only — staff/POS
            // ordering (orders.create) is deliberately unaffected.
            $table->boolean('ordering_enabled')->default(true)->after('accepts_cash_on_pickup');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('ordering_enabled');
        });
    }
};
