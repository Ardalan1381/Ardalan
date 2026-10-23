<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['spend_threshold', 'buy_x_get_y_points']);
            $table->unsignedBigInteger('min_spend_amount')->nullable();
            $table->unsignedInteger('points_earned')->nullable();
            $table->unsignedInteger('buy_quantity')->nullable();
            $table->unsignedInteger('get_bonus_points')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
