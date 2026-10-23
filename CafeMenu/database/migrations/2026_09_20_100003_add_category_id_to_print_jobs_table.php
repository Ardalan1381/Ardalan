<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            // Resolved once at dispatch time and stored, rather than
            // re-derived from PrintRoute when the job actually executes —
            // so a route changing after dispatch never changes what an
            // already-queued job prints. Null means "no category filter"
            // (a catch-all route, or a receipt/invoice, which has no
            // category concept at all).
            $table->foreignId('category_id')->nullable()->after('document_type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
