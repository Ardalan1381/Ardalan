<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('reason')->nullable();

            // 'completed' is immediate for cash/manual refunds; 'pending'/
            // 'failed' are reserved for a future real gateway-reversal call
            // — not built in this phase (none of the three payment gateway
            // integrations in this app expose a reversal endpoint today).
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->enum('refund_method', ['original_payment_method', 'cash', 'store_credit'])->default('original_payment_method');
            $table->foreignId('processed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
