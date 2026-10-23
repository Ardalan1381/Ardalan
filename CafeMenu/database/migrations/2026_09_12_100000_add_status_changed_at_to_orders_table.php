<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Set explicitly by PlaceOrderAction/TransitionOrderStatusAction
            // on every status change — deliberately not Eloquent's
            // auto-touched updated_at, which order-editing (item additions,
            // discount recalculation) must not reset the Kitchen Display's
            // "time in this status" clock.
            $table->timestamp('status_changed_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('status_changed_at');
        });
    }
};
