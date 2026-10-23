<?php

namespace App\Http\Controllers;

use App\Actions\UpdatePaymentStatusAction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentCallbackController extends Controller
{
    /**
     * Public, unauthenticated, CSRF-exempt (see bootstrap/app.php) — gateways
     * redirect or POST back here directly, cross-origin. The callback's own
     * query/body parameters are never trusted as proof of payment; the
     * gateway-specific token is used only to look up which pending Payment
     * this is about, and the actual outcome always comes from a fresh
     * server-to-server verify() call to the gateway itself.
     */
    public function handle(Request $request, string $gateway, PaymentGatewayManager $manager, UpdatePaymentStatusAction $updateStatus): View
    {
        $token = $request->input('Authority') ?? $request->input('trackId') ?? $request->input('RefId');

        $payment = Payment::where('gateway', $gateway)->where('gateway_token', $token)->first();

        if (! $payment) {
            return view('payments.status', ['status' => 'verification_failed']);
        }

        return DB::transaction(function () use ($request, $payment, $gateway, $manager, $updateStatus) {
            // Order row first, then payment: the lock order every payment
            // write follows, so a callback racing a cashier cannot deadlock.
            Order::query()->whereKey($payment->order_id)->lockForUpdate()->first();
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Replayed/duplicate callback delivery: the outcome was already
            // recorded, so render it again without re-verifying over the
            // network or re-running the status-transition logic.
            if ($locked->status !== 'pending') {
                return view('payments.status', [
                    'status' => $locked->status === 'paid' ? 'success' : 'failed',
                    'order' => $locked->order,
                ]);
            }

            $result = $manager->driver($gateway)->verify($request, $locked);

            if (! $result->successful) {
                $updateStatus->execute($locked, 'failed');

                return view('payments.status', ['status' => 'failed', 'order' => $locked->order]);
            }

            if ($result->gatewayReference) {
                $locked->reference = $result->gatewayReference;
                $locked->save();
            }

            $updateStatus->execute($locked, 'paid');

            return view('payments.status', ['status' => 'success', 'order' => $locked->order]);
        });
    }
}
