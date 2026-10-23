<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_routes', function (Blueprint $table) {
            $table->id();
            $table->enum('document_type', ['receipt', 'kitchen_docket', 'bar_docket', 'invoice']);
            $table->foreignId('printer_id')->constrained()->restrictOnDelete();

            // Nullable: a category-scoped route sends only that category's
            // items to this printer (e.g. drinks -> bar, food -> kitchen);
            // a route with no category is the catch-all/default for its
            // document_type.
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_routes');
    }
};
