<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // Only one template may have is_default=true at a time —
            // enforced by SetDefaultInvoiceTemplateAction, the same pattern
            // MediaUploader::makePrimary() already uses for primary images.
            $table->boolean('is_default')->default(false);

            $table->text('header_html')->nullable();
            $table->text('footer_html')->nullable();
            $table->enum('paper_size', ['a4', 'thermal_58mm', 'thermal_80mm'])->default('a4');
            $table->boolean('show_logo')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }
};
