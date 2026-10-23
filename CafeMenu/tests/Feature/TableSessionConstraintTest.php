<?php

namespace Tests\Feature;

use App\Models\Table;
use App\Models\TableSession;
use App\Services\TableSessionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUD-06: the database itself refuses a second open session for a table,
 * whatever code path tries to create one. (The multi-process race is in
 * tests/Concurrency.)
 */
class TableSessionConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_open_session_for_the_same_table_is_rejected_by_the_database(): void
    {
        $table = Table::factory()->create();
        TableSession::create(['table_id' => $table->id, 'status' => 'open', 'opened_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        TableSession::create(['table_id' => $table->id, 'status' => 'open', 'opened_at' => now()]);
    }

    public function test_closed_sessions_do_not_count_towards_the_constraint(): void
    {
        $table = Table::factory()->create();
        TableSession::create(['table_id' => $table->id, 'status' => 'closed', 'opened_at' => now(), 'closed_at' => now()]);
        TableSession::create(['table_id' => $table->id, 'status' => 'closed', 'opened_at' => now(), 'closed_at' => now()]);

        $session = app(TableSessionService::class)->openOrReuse($table);

        $this->assertSame($session->id, app(TableSessionService::class)->openOrReuse($table)->id);
        $this->assertSame(1, TableSession::where('table_id', $table->id)->where('status', 'open')->count());
    }
}
