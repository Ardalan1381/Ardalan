<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ProcessRefundAction;
use App\Actions\ResolveRefundAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveRefundRequest;
use App\Http\Requests\Admin\StoreRefundRequest;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    public function store(StoreRefundRequest $request, Payment $payment, ProcessRefundAction $action): RedirectResponse
    {
        try {
            $action->execute($payment, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.show', $payment->order)->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.show', $payment->order)->with('status', __('admin.flash.refund_processed'));
    }

    public function resolve(ResolveRefundRequest $request, Refund $refund, ResolveRefundAction $action): RedirectResponse
    {
        $data = $request->validated();

        try {
            $action->execute($refund, $data['outcome'], $request->user(), $data['external_reference'] ?? null, $data['resolution_note'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.show', $refund->order)->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.show', $refund->order)->with('status', __('admin.flash.refund_resolved'));
    }
}
