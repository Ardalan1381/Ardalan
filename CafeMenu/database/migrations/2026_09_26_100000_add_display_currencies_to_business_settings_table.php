<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            // A small admin-managed map of locale => {code, symbol,
            // minor_units, is_active} for locales OTHER than the business's
            // own (home) locale — a manually-priced reference currency shown
            // on the menu when browsing in that language. The home locale
            // always uses `currency`/`currency_minor_units` above; this is
            // purely additive and never touched by order/payment/report
            // logic, which stay single-currency exactly as before.
            $table->json('display_currencies')->nullable()->after('ordering_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('display_currencies');
        });
    }
};
