<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One manually-entered price override per product per additional
     * display locale (see business_settings.display_currencies) — e.g. a
     * product priced at 65,000 Toman (its base_price, unchanged) might also
     * carry an explicit $2.50 row here for the "en" locale. No conversion
     * is ever computed from this; a product simply has no row for a locale
     * until staff type one in, and the menu falls back to the home price
     * when that happens.
     */
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('discount_price')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
