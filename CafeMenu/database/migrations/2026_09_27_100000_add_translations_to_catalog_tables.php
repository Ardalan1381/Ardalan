<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A manually-entered translation override per locale, for the same
     * reason product_prices works this way (see that migration): staff type
     * a name/description for each additional language, there is no
     * automatic translation, and the business's own home locale keeps using
     * the existing plain `name`/`description` columns untouched — this is
     * purely additive.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('description');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('description');
        });

        Schema::table('product_option_groups', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('name');
        });

        Schema::table('product_options', function (Blueprint $table) {
            $table->json('translations')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('translations'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('translations'));
        Schema::table('product_option_groups', fn (Blueprint $table) => $table->dropColumn('translations'));
        Schema::table('product_options', fn (Blueprint $table) => $table->dropColumn('translations'));
    }
};
