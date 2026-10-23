<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforces "at most one OPEN session per table" in the database itself.
     *
     * MySQL/MariaDB have no partial indexes, so a virtual generated column
     * holds table_id only while the session is open (NULL otherwise — and a
     * UNIQUE index allows any number of NULLs). SQLite/PostgreSQL use a
     * partial unique index directly.
     *
     * Existing duplicates are never merged or closed automatically: which
     * session's orders belong where is a business decision, so the
     * migration stops with the offending table ids instead.
     */
    public function up(): void
    {
        $duplicates = DB::table('table_sessions')
            ->where('status', 'open')
            ->groupBy('table_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('table_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the one-open-session-per-table constraint: tables '.$duplicates->implode(', ')
                .' have more than one open session. Merge or close the extra sessions from the admin panel, then re-run the migration.'
            );
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('table_sessions', function (Blueprint $table) {
                $table->unsignedBigInteger('open_table_id')->nullable()->virtualAs("CASE WHEN status = 'open' THEN table_id END");
                $table->unique('open_table_id', 'table_sessions_one_open_per_table');
            });

            return;
        }

        DB::statement("CREATE UNIQUE INDEX table_sessions_one_open_per_table ON table_sessions (table_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('table_sessions', function (Blueprint $table) {
                $table->dropUnique('table_sessions_one_open_per_table');
                $table->dropColumn('open_table_id');
            });

            return;
        }

        DB::statement('DROP INDEX table_sessions_one_open_per_table');
    }
};
