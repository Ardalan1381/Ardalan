<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation');
            $table->enum('type', ['mass', 'volume', 'count']);

            // Size of one of this unit relative to that type's canonical
            // base unit (gram for mass, milliliter for volume, piece for
            // count) — e.g. kilogram has base_factor 1000 relative to gram.
            // Converting between two units of the SAME type is then just
            // source_qty * (source.base_factor / target.base_factor).
            $table->decimal('base_factor', 20, 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
