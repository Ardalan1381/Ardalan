<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('connection_type', ['network', 'usb']);
            $table->string('ip_address')->nullable();
            $table->unsignedInteger('port')->nullable()->default(9100);
            $table->string('device_path')->nullable();
            $table->enum('paper_width', ['58mm', '80mm'])->default('80mm');

            // ESC/POS character-code-table number this printer should
            // switch to (via "ESC t n") before printing Farsi content —
            // there is no single number that means "Farsi/CP1256" across
            // all manufacturers, so this is per-printer and operator-set
            // (commonly 22 on many generic/Xprinter-clone firmwares, but
            // must be confirmed against the specific hardware's manual).
            // Left null, no codepage switch is sent and the printer's own
            // default (usually CP437/USA) is used, which cannot render
            // Farsi correctly.
            $table->unsignedTinyInteger('farsi_codepage_table')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};
