<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('product_name_snapshot');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_snapshot');
            $table->unsignedBigInteger('discount_amount_snapshot')->default(0);
            $table->unsignedBigInteger('line_total_snapshot');

            // Flattened text (e.g. "Extra Shot, Oat Milk") — avoids a
            // further nested table purely for print/display purposes.
            $table->string('option_summary_snapshot')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_line_items');
    }
};
