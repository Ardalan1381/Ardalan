<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'expire', 'manual_adjustment']);
            $table->integer('points');
            $table->integer('resulting_balance');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loyalty_rule_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('loyalty_reward_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_ledger_entries');
    }
};
