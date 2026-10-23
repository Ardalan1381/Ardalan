<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TableSessionService
{
    /**
     * Opens a new session for the table, or returns its already-open one.
     *
     * Serialised on the TABLE row: locking the (possibly absent) open
     * session row is not enough — on InnoDB, two transactions can both
     * take a gap lock on "no open session yet" and then deadlock (or, at
     * READ COMMITTED, both insert). The unique open-session index added in
     * 2026_10_23_100001 is the last line of defence behind this lock.
     */
    public function openOrReuse(Table $table, ?User $openedBy = null): TableSession
    {
        return DB::transaction(function () use ($table, $openedBy) {
            Table::query()->whereKey($table->id)->lockForUpdate()->first();

            $existing = TableSession::query()->where('table_id', $table->id)->open()->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            return TableSession::create([
                'table_id' => $table->id,
                'status' => 'open',
                'opened_at' => now(),
                'opened_by_user_id' => $openedBy?->id,
            ]);
        });
    }

    public function close(TableSession $session, User $closedBy): TableSession
    {
        if ($session->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'This session is already closed.']);
        }

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_user_id' => $closedBy->id,
        ]);

        return $session;
    }

    public function transfer(TableSession $session, Table $targetTable): TableSession
    {
        return DB::transaction(function () use ($session, $targetTable) {
            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'Only open sessions can be transferred.']);
            }

            if ($targetTable->id === $session->table_id) {
                throw ValidationException::withMessages(['table_id' => 'Select a different table to transfer to.']);
            }

            Table::query()->whereKey($targetTable->id)->lockForUpdate()->first();

            $targetHasOpenSession = TableSession::query()->where('table_id', $targetTable->id)->open()->lockForUpdate()->exists();

            if ($targetHasOpenSession) {
                throw ValidationException::withMessages(['table_id' => 'The target table already has an open session.']);
            }

            $session->update(['table_id' => $targetTable->id]);

            // Orders read their table two ways: directly via table_id, and
            // indirectly via tableSession->table. Without this, the two
            // would silently disagree the moment an order placed before the
            // transfer is displayed afterward.
            Order::query()->where('table_session_id', $session->id)->update(['table_id' => $targetTable->id]);

            return $session;
        });
    }

    /**
     * Merges the source session's orders into the destination session, then
     * closes the source (bill-splitting itself is out of scope — this only
     * consolidates which session orders belong to).
     */
    public function merge(TableSession $source, TableSession $destination, User $mergedBy): TableSession
    {
        return DB::transaction(function () use ($source, $destination, $mergedBy) {
            if ($source->id === $destination->id) {
                throw ValidationException::withMessages(['destination' => 'Cannot merge a session into itself.']);
            }

            if ($source->status !== 'open' || $destination->status !== 'open') {
                throw ValidationException::withMessages(['session' => 'Both sessions must be open to merge.']);
            }

            // Reassign both the session link AND the direct table_id — the
            // same consistency requirement as transfer() above, since a
            // merge also moves orders to a (potentially) different table.
            Order::query()->where('table_session_id', $source->id)->update([
                'table_session_id' => $destination->id,
                'table_id' => $destination->table_id,
            ]);

            $source->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by_user_id' => $mergedBy->id,
                'merged_into_session_id' => $destination->id,
            ]);

            return $destination->fresh();
        });
    }
}
