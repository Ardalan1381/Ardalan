<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();

            // Nullable: generated from the row's own auto-increment id
            // after the initial insert (see Purchase::booted()), the same
            // gap-safe technique Order::order_number already uses.
            $table->string('purchase_number')->unique()->nullable();
            $table->enum('status', ['draft', 'ordered', 'partially_received', 'received', 'cancelled'])->default('draft');
            $table->date('purchase_date');
            $table->date('expected_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_total')->default(0);
            $table->unsignedBigInteger('total_cost')->default(0);
            $table->enum('payment_status', ['unpaid', 'partially_paid', 'paid'])->default('unpaid');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
