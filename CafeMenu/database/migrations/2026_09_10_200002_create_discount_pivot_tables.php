<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_products', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'product_id']);
        });

        Schema::create('discount_categories', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'category_id']);
        });

        Schema::create('discount_customers', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'customer_id']);
        });

        Schema::create('discount_customer_groups', function (Blueprint $table) {
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['discount_id', 'customer_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_customer_groups');
        Schema::dropIfExists('discount_customers');
        Schema::dropIfExists('discount_categories');
        Schema::dropIfExists('discount_products');
    }
};
