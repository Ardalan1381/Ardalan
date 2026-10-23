<?php

namespace App\Actions;

use App\Actions\Shifts\RecordCashMovementAction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a full or partial refund against an existing, paid Payment. The
 * original Payment row is NEVER mutated (no status flip, no amount
 * change) — this is the historical proof the money was originally taken;
 * Refund rows are the append-only record of money given back.
 *
 * No payment-gateway reversal call is made here: none of the three gateway
 * integrations in this app (Mellat/Zarinpal/Zibal) has a refund API wired
 * up. So only a refund paid out in cash at the counter is recorded as
 * 'completed' straight away. Every other refund (card/online reversal,
 * store credit) is recorded as 'pending' — it reserves the amount but does
 * not reduce what the order counts as paid — until a staff member confirms
 * the money was actually returned elsewhere (ResolveRefundAction).
 */
class ProcessRefundAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordCashMovementAction $recordCashMovement,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(Payment $payment, array $data, User $actor): Refund
    {
        $amount = (int) $data['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The refund amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($payment, $data, $actor, $amount) {
            // Same lock order as every other payment write: order, then payment.
            Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => 'Only a paid payment can be refunded.',
                ]);
            }

            if ($amount > $locked->refundableAmount()) {
                throw ValidationException::withMessages([
                    'amount' => "This refund would exceed the refundable balance ({$locked->refundableAmount()}).",
                ]);
            }

            $refundMethod = $data['refund_method'] ?? 'original_payment_method';
            $completed = Refund::settlesImmediately($refundMethod, $locked);

            $refund = Refund::create([
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'status' => $completed ? 'completed' : 'pending',
                'refund_method' => $refundMethod,
                'processed_by_user_id' => $actor->id,
                'processed_at' => now(),
            ]);

            $locked->order->syncPaymentStatus();

            $locked->billSplitPart?->recalculate();

            if ($completed && ($refundMethod === 'cash' || $locked->method === 'cash')) {
                $shift = Shift::query()->where('user_id', $actor->id)->open()->first();

                if ($shift) {
                    $this->recordCashMovement->execute($shift, 'refund', -$amount, $actor, reference: $refund);
                }
            }

            $this->auditLogger->log('payment.refunded', $refund, new: [
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'amount' => $amount,
                'refund_method' => $refundMethod,
                'status' => $refund->status,
            ]);

            return $refund;
        });
    }
}
