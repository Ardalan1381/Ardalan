<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 4);
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->enum('reason', ['spoilage', 'breakage', 'expired', 'preparation_error', 'other']);
            $table->text('notes')->nullable();
            $table->dateTime('wasted_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inventory_item_id', 'wasted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_records');
    }
};
