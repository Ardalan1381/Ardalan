<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->morphs('printable'); // Order (receipt/docket) or Invoice
            $table->enum('document_type', ['receipt', 'kitchen_docket', 'bar_docket', 'invoice']);
            $table->foreignId('printer_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
