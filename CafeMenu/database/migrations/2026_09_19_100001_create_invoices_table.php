<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number')->unique()->nullable();
            $table->dateTime('issued_at');

            // Double-snapshotted from the order's OWN snapshot fields at
            // generation time — an invoice is a financial document and must
            // never change even if something upstream theoretically could.
            $table->string('customer_name_snapshot')->nullable();
            $table->string('customer_phone_snapshot')->nullable();
            $table->string('table_name_snapshot')->nullable();
            $table->string('waiter_name_snapshot')->nullable();

            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total');
            $table->unsignedBigInteger('tax_total');
            $table->unsignedBigInteger('service_charge_total');
            $table->unsignedBigInteger('grand_total');
            $table->string('currency', 10);

            $table->foreignId('template_id')->nullable()->constrained('invoice_templates')->nullOnDelete();
            $table->foreignId('generated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
