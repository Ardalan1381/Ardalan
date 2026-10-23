<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->morphs('recipable'); // Product or ProductOption
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();

            // Consumed per ONE unit sold of the recipable, in $unit_id —
            // converted to the inventory item's own base unit at
            // consumption time.
            $table->decimal('quantity', 14, 4);
            $table->foreignId('unit_id')->constrained('inventory_units')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['recipable_type', 'recipable_id', 'inventory_item_id'], 'recipe_items_recipable_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
