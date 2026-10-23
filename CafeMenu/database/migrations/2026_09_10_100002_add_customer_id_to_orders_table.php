<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Purely additive back-reference — customer_name/customer_phone
            // remain the historical snapshot and are never replaced by this
            // relation. Null means "guest", not "unknown customer".
            $table->foreignId('customer_id')->nullable()->after('table_session_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
