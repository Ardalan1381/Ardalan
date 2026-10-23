<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->enum('type', [
                'purchase_receipt',
                'sale_consumption',
                'sale_reversal',
                'adjustment_increase',
                'adjustment_decrease',
                'waste',
                'return_to_supplier',
                'customer_return',
                'opening_balance',
                'count_correction',
            ]);

            // Signed delta in the item's base unit — positive increases
            // stock, negative decreases it, uniformly regardless of type.
            // This table is the single source of truth; inventory_items.
            // current_stock is only ever a cache of the running sum here.
            $table->decimal('quantity', 14, 4);
            $table->decimal('resulting_stock', 14, 4);
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->nullableMorphs('reference');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_item_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
