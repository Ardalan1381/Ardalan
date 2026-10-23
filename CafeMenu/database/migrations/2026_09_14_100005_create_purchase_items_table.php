<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();

            // The unit purchased in — may differ from the item's base_unit
            // (e.g. buying a 5kg sack for an item tracked in grams).
            $table->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();

            $table->decimal('quantity', 14, 4);
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('line_total');
            $table->decimal('quantity_received', 14, 4)->default(0);
            $table->timestamps();

            $table->index('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
