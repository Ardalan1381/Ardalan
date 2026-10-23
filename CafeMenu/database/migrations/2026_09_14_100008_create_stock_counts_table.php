<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->enum('status', ['draft', 'completed'])->default('draft');
            $table->dateTime('counted_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();

            // Snapshotted when the item is added to the count, so a
            // variance can be computed even if current_stock moves before
            // the count is completed.
            $table->decimal('expected_quantity', 14, 4);
            $table->decimal('counted_quantity', 14, 4)->nullable();
            $table->decimal('variance', 14, 4)->nullable();
            $table->timestamps();

            $table->index('stock_count_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
    }
};
