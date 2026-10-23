<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Shifts\CloseShiftAction;
use App\Actions\Shifts\OpenShiftAction;
use App\Actions\Shifts\RecordCashMovementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseShiftRequest;
use App\Http\Requests\Admin\OpenShiftRequest;
use App\Http\Requests\Admin\RecordCashMovementRequest;
use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\TableSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShiftController extends Controller
{
    /**
     * Serves both audiences from one page: a shifts.view_own-only user sees
     * their own history plus an open-shift form/link, while a
     * shifts.view_all user (manager/owner) additionally sees every staff
     * member's shifts.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Shift::class);

        $user = auth()->user();
        $perPage = SystemSetting::get('pagination_per_page', 20);

        $currentShift = Shift::query()->where('user_id', $user->id)->open()->first();

        $ownShifts = Shift::query()
            ->where('user_id', $user->id)
            ->latest('opened_at')
            ->paginate($perPage, ['*'], 'own_page');

        $allShifts = null;
        if ($user->hasPermission('shifts.view_all')) {
            $allShifts = Shift::query()
                ->with('user')
                ->latest('opened_at')
                ->paginate($perPage, ['*'], 'all_page');
        }

        return view('admin.shifts.index', [
            'currentShift' => $currentShift,
            'ownShifts' => $ownShifts,
            'allShifts' => $allShifts,
        ]);
    }

    public function store(OpenShiftRequest $request, OpenShiftAction $action): RedirectResponse
    {
        try {
            $shift = $action->execute($request->user(), (int) $request->validated('opening_cash_amount'));
        } catch (ValidationException $e) {
            return redirect()->route('admin.shifts.index')->withErrors($e->errors());
        }

        return redirect()->route('admin.shifts.show', $shift)->with('status', __('admin.flash.shift_opened'));
    }

    public function show(Shift $shift): View
    {
        $this->authorize('view', $shift);

        $shift->load(['user', 'cashMovements' => fn ($query) => $query->latest()->with(['createdBy', 'reference'])]);

        // Informational only — a shift may still be closed with unpaid
        // sessions outstanding; the count is just a heads-up for the closer.
        $openUnpaidSessionsCount = TableSession::query()
            ->open()
            ->whereHas('orders', fn ($query) => $query->where('payment_status', '!=', 'paid'))
            ->count();

        return view('admin.shifts.show', [
            'shift' => $shift,
            'openUnpaidSessionsCount' => $openUnpaidSessionsCount,
        ]);
    }

    public function recordCashMovement(RecordCashMovementRequest $request, Shift $shift, RecordCashMovementAction $action): RedirectResponse
    {
        try {
            $action->execute(
                $shift,
                $request->validated('type'),
                $request->signedAmount(),
                $request->user(),
                reason: $request->validated('reason'),
            );
        } catch (ValidationException $e) {
            return redirect()->route('admin.shifts.show', $shift)->withErrors($e->errors());
        }

        return redirect()->route('admin.shifts.show', $shift)->with('status', __('admin.flash.cash_movement_recorded'));
    }

    public function close(CloseShiftRequest $request, Shift $shift, CloseShiftAction $action): RedirectResponse
    {
        try {
            $action->execute($shift, (int) $request->validated('closing_cash_amount'), $request->validated('notes'));
        } catch (ValidationException $e) {
            return redirect()->route('admin.shifts.show', $shift)->withErrors($e->errors());
        }

        return redirect()->route('admin.shifts.show', $shift)->with('status', __('admin.flash.shift_closed'));
    }
}
