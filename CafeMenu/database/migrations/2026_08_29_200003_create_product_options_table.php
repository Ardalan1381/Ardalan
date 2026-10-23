<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_option_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('price_delta')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_option_group_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
