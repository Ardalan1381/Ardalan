<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordPaymentAction;
use App\Actions\UpdatePaymentStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentStatusRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Payment::class);

        $payments = Payment::query()
            ->with('order')
            ->latest()
            ->paginate(SystemSetting::get('pagination_per_page', 20));

        return view('admin.payments.index', ['payments' => $payments]);
    }

    public function store(StorePaymentRequest $request, Order $order, RecordPaymentAction $action): RedirectResponse
    {
        try {
            $action->execute($order, $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.show', $order)->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.show', $order)->with('status', __('admin.flash.payment_recorded'));
    }

    public function updateStatus(UpdatePaymentStatusRequest $request, Payment $payment, UpdatePaymentStatusAction $action): RedirectResponse
    {
        try {
            $action->execute($payment, $request->validated('status'), $request->user());
        } catch (ValidationException $e) {
            return redirect()->route('admin.orders.show', $payment->order)->withErrors($e->errors());
        }

        return redirect()->route('admin.orders.show', $payment->order)->with('status', __('admin.flash.payment_status_updated'));
    }
}
