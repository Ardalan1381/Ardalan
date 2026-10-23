<?php

namespace App\Actions;

use App\Actions\Shifts\RecordCashMovementAction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a payment against an order. No gateway is integrated — amounts
 * are entered by staff (cash counted, POS receipt, "pay later" tab) — but
 * overpayment and invalid amounts are still enforced server-side.
 */
class RecordPaymentAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordCashMovementAction $recordCashMovement,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Order $order, array $data, User $user): Payment
    {
        $amount = (int) $data['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The payment amount must be greater than zero.']);
        }

        $status = $data['status'] ?? 'pending';

        return DB::transaction(function () use ($order, $data, $user, $amount, $status) {
            if ($status === 'paid') {
                $this->assertNoOverpayment($order, $amount);
            }

            $payment = $order->payments()->create([
                'method' => $data['method'],
                'amount' => $amount,
                'status' => $status,
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'recorded_by_user_id' => $user->id,
                'paid_at' => $status === 'paid' ? now() : null,
            ]);

            $order->syncPaymentStatus();

            if ($payment->method === 'cash' && $payment->status === 'paid') {
                $shift = Shift::query()->where('user_id', $user->id)->open()->first();

                if ($shift) {
                    $this->recordCashMovement->execute($shift, 'sale', $payment->amount, $user, reference: $payment);
                }
            }

            $this->auditLogger->log('payment.recorded', $payment, new: [
                'order_id' => $order->id,
                'method' => $payment->method,
                'amount' => $payment->amount,
                'status' => $payment->status,
            ]);

            return $payment;
        });
    }

    private function assertNoOverpayment(Order $order, int $additionalAmount): void
    {
        $alreadyPaid = $order->totalPaid();

        if ($alreadyPaid + $additionalAmount > $order->grand_total) {
            $remaining = $order->grand_total - $alreadyPaid;

            throw ValidationException::withMessages([
                'amount' => "This payment would exceed the order total. Remaining balance: {$remaining}.",
            ]);
        }
    }
}
