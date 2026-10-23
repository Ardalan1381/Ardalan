<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('points_cost');
            $table->enum('reward_type', ['fixed_discount_amount', 'percentage_discount', 'free_product']);

            // minor-units for fixed_discount_amount, bps for percentage_discount,
            // null for free_product (its value is the product's own price).
            $table->unsignedInteger('value')->nullable();
            $table->foreignId('free_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rewards');
    }
};
