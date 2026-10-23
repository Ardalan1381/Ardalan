<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-item consumption marker, so items added to an already-confirmed
     * order can be consumed on their own without touching the items that
     * were consumed before (see RecipeConsumptionService).
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dateTime('inventory_consumed_at')->nullable()->after('item_notes');
        });

        // Items of orders that were already consumed under the old,
        // order-level-only marker count as consumed too.
        DB::table('order_items')
            ->whereIn('order_id', DB::table('orders')->whereNotNull('inventory_consumed_at')->select('id'))
            ->update(['inventory_consumed_at' => DB::raw('(SELECT inventory_consumed_at FROM orders WHERE orders.id = order_items.order_id)')]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('inventory_consumed_at');
        });
    }
};
