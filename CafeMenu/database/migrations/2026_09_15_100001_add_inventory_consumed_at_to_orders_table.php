<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Idempotency guard so RecipeConsumptionService can never
            // double-deduct (or double-reverse) stock for the same order.
            $table->timestamp('inventory_consumed_at')->nullable()->after('status_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('inventory_consumed_at');
        });
    }
};
