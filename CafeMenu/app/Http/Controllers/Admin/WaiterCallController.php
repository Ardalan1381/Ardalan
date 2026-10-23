<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResolveWaiterCallAction;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\WaiterCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WaiterCallController extends Controller
{
    public function index(): View
    {
        $this->authorize('waiter_calls.view');

        $waiterCalls = WaiterCall::query()
            ->with('table.section', 'resolvedBy')
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.waiter-calls.index', ['waiterCalls' => $waiterCalls]);
    }

    public function resolve(Request $request, WaiterCall $waiterCall, ResolveWaiterCallAction $action): RedirectResponse|JsonResponse
    {
        $this->authorize('waiter_calls.manage');

        try {
            $action->execute($waiterCall, $request->user());
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
            }

            return redirect()->route('admin.waiter-calls.index')->withErrors($e->errors());
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'resolved',
                'resolved_by' => $request->user()->name,
                'message' => __('admin.flash.waiter_call_resolved'),
            ]);
        }

        return redirect()->route('admin.waiter-calls.index')->with('status', __('admin.flash.waiter_call_resolved'));
    }
}
