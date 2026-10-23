<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'item_discount', 'half_price', 'buy_x_get_y', 'free_item']);

            // bps for percentage, minor-units for fixed_amount/item_discount,
            // null for half_price/buy_x_get_y/free_item (their amount is
            // derived, not configured).
            $table->unsignedInteger('value')->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_quantity')->nullable();
            $table->foreignId('free_item_product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->enum('applies_to', ['order', 'specific_products', 'specific_categories'])->default('order');

            $table->integer('priority')->default(0);
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_active')->default(true);

            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->json('days_of_week')->nullable();

            $table->unsignedBigInteger('min_order_amount')->nullable();
            $table->unsignedInteger('min_quantity')->nullable();

            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
    }
};
