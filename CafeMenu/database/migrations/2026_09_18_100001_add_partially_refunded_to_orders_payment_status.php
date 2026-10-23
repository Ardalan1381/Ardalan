<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite has no ALTER COLUMN for a CHECK-constrained enum column
            // without recreating it — Laravel's native (non-doctrine/dbal)
            // SQLite grammar supports add/drop/rename column, so a
            // copy-and-swap achieves the same result portably.
            Schema::table('orders', function (Blueprint $table) {
                $table->string('payment_status_new')->default('pending')->after('payment_status');
            });

            DB::statement('UPDATE orders SET payment_status_new = payment_status');

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('payment_status');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->renameColumn('payment_status_new', 'payment_status');
            });

            return;
        }

        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'partially_refunded') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE orders MODIFY payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
    }
};
