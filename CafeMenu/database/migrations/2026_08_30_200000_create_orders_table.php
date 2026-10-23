<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('order_number')->unique()->nullable();
            $table->enum('type', ['dine_in', 'takeaway', 'pickup', 'delivery']);
            $table->foreignId('table_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('service_charge_total')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->string('currency', 10);
            $table->enum('status', [
                'pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled', 'rejected',
            ])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
