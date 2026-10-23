<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MergeTableSessionRequest;
use App\Http\Requests\Admin\TransferTableSessionRequest;
use App\Models\Table;
use App\Models\TableSession;
use App\Services\TableSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TableSessionController extends Controller
{
    public function __construct(private readonly TableSessionService $tableSessionService) {}

    public function show(Table $table): View
    {
        $this->authorize('tables.manage');

        $table->load([
            'sessions' => fn ($query) => $query->latest('opened_at')->limit(10),
            'sessions.orders',
        ]);

        return view('admin.tables.session', [
            'table' => $table,
            'openTableSessions' => TableSession::query()->open()->where('table_id', '!=', $table->id)->with('table')->get(),
        ]);
    }

    public function open(Table $table): RedirectResponse
    {
        $this->authorize('tables.manage');

        $this->tableSessionService->openOrReuse($table, request()->user());

        return redirect()->route('admin.tables.session', $table)->with('status', __('admin.flash.session_opened'));
    }

    public function close(TableSession $tableSession): RedirectResponse
    {
        $this->authorize('tables.manage');

        try {
            $this->tableSessionService->close($tableSession, request()->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.tables.session', $tableSession->table)->withErrors($e->errors());
        }

        return redirect()->route('admin.tables.session', $tableSession->table)->with('status', __('admin.flash.session_closed'));
    }

    public function transfer(TransferTableSessionRequest $request, TableSession $tableSession): RedirectResponse
    {
        $targetTable = Table::findOrFail($request->validated('table_id'));

        try {
            $this->tableSessionService->transfer($tableSession, $targetTable);
        } catch (ValidationException $e) {
            return redirect()->route('admin.tables.session', $tableSession->table)->withErrors($e->errors());
        }

        return redirect()->route('admin.tables.session', $targetTable)->with('status', __('admin.flash.session_transferred'));
    }

    public function merge(MergeTableSessionRequest $request, TableSession $tableSession): RedirectResponse
    {
        $destination = TableSession::findOrFail($request->validated('destination_session_id'));

        try {
            $merged = $this->tableSessionService->merge($tableSession, $destination, $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.tables.session', $tableSession->table)->withErrors($e->errors());
        }

        return redirect()->route('admin.tables.session', $merged->table)->with('status', __('admin.flash.sessions_merged'));
    }
}
