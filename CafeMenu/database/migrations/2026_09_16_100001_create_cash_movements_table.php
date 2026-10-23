<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cash_in', 'cash_out', 'sale', 'refund', 'adjustment']);

            // Signed — cash_in/sale positive, cash_out/refund negative,
            // adjustment either way.
            $table->bigInteger('amount');
            $table->nullableMorphs('reference');
            $table->text('reason')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['shift_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
