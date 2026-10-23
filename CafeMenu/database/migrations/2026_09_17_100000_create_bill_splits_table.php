<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_splits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // "by customer" is a labeled by_item split, not a separate code
            // path — a part's label carries the customer's name/seat.
            $table->enum('split_type', ['by_item', 'by_amount', 'even']);
            $table->enum('status', ['open', 'settled'])->default('open');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_splits');
    }
};
