<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\Gateways\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InitiatePaymentController extends Controller
{
    public function store(Order $order, string $gateway, PaymentGatewayManager $manager): RedirectResponse|View
    {
        abort_unless(in_array($gateway, $manager->availableForCustomers(), true), 404);

        $payment = DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->balanceDue() <= 0) {
                return null;
            }

            // Supersede any stale pending online payment for this order (e.g.
            // the customer opened "pay online" twice) so balanceDue() can
            // never be double-counted against two live pending attempts.
            Payment::where('order_id', $lockedOrder->id)
                ->where('method', 'online')
                ->where('status', 'pending')
                ->update(['status' => 'failed']);

            return $lockedOrder->payments()->create([
                'method' => 'online',
                'amount' => $lockedOrder->balanceDue(),
                'status' => 'pending',
            ]);
        });

        if (! $payment) {
            return redirect()->route('orders.show', $order)->with('error', __('payments.pay_online.nothing_due'));
        }

        $payment->gateway = $gateway;
        $payment->save();

        try {
            $result = $manager->driver($gateway)->initiate($payment, route('payments.callback', $gateway));
        } catch (PaymentGatewayException $e) {
            report($e);

            return redirect()->route('orders.show', $order)->with('error', __('payments.pay_online.failed_to_start'));
        }

        if ($result->method === 'GET') {
            return redirect()->away($result->url);
        }

        return view('payments.redirect-form', ['url' => $result->url, 'fields' => $result->fields]);
    }
}
