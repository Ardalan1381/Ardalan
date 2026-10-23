<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('waiter_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->enum('source', ['customer_qr', 'staff_pos'])->default('customer_qr')->after('waiter_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waiter_id');
            $table->dropColumn('source');
        });
    }
};
