<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A refund that has to be carried out outside this application (card
     * terminal reversal, gateway dashboard, bank transfer, issuing store
     * credit by hand) is now recorded as 'pending' until a staff member
     * confirms it was actually settled — or marks it 'failed'. These columns
     * record who resolved it, when, and the external proof.
     */
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('resolved_by_user_id')->nullable()->after('processed_at')->constrained('users')->restrictOnDelete();
            $table->dateTime('resolved_at')->nullable()->after('resolved_by_user_id');
            $table->string('external_reference')->nullable()->after('resolved_at');
            $table->text('resolution_note')->nullable()->after('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['resolved_at', 'external_reference', 'resolution_note']);
        });
    }
};
