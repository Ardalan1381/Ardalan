<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Client-generated key for one checkout attempt (see PlaceOrderAction).
     * The UNIQUE index is what actually guarantees one order per key when
     * a double-click or a retried request arrives concurrently; the
     * fingerprint detects a key being reused for a different cart.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('uuid');
            $table->char('idempotency_fingerprint', 64)->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'idempotency_fingerprint']);
        });
    }
};
