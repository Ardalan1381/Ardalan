<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_split_part_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_split_part_id')->constrained()->cascadeOnDelete();

            // UNIQUE, not just indexed — this single DB-level constraint is
            // the "never charge the same item twice" guarantee: one
            // order_item can belong to at most one split part, enforced
            // regardless of any application-level bug.
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedInteger('quantity_allocated');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_split_part_items');
    }
};
