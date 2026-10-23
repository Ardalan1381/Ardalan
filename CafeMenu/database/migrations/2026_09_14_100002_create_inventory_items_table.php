<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_category_id')->nullable()->constrained('inventory_categories')->restrictOnDelete();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->foreignId('base_unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->decimal('min_stock_level', 14, 4)->nullable();
            $table->decimal('max_stock_level', 14, 4)->nullable();

            // Cached, sourced exclusively from stock_movements — never
            // written directly by anything except StockMovementService.
            $table->decimal('current_stock', 14, 4)->default(0);

            // Minor-units of currency per one base unit, weighted moving
            // average recomputed on every purchase_receipt movement.
            $table->unsignedBigInteger('average_cost')->nullable();

            $table->timestamp('low_stock_alerted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('inventory_category_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
