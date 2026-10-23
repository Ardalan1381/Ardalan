<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway')->nullable()->after('method');
            $table->string('gateway_token')->nullable()->after('gateway');

            // Nullable-safe: cash/card/pay_later payments keep gateway_token
            // NULL, and both MySQL and Postgres allow multiple NULLs under a
            // unique index. This is the real idempotency anchor for gateway
            // callbacks — see PaymentCallbackController.
            $table->unique(['gateway', 'gateway_token']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['gateway', 'gateway_token']);
            $table->dropColumn(['gateway', 'gateway_token']);
        });
    }
};
