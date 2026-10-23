<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_split_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_split_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedBigInteger('amount_due');
            $table->unsignedBigInteger('amount_paid')->default(0);
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->timestamps();

            $table->index('bill_split_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_split_parts');
    }
};
