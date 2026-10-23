<?php

namespace App\Actions;

use App\Actions\Shifts\RecordCashMovementAction;
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
 * No real payment-gateway reversal call is made here: none of the three
 * gateway integrations in this app (Mellat/Zarinpal/Zibal) expose a
 * reversal/refund API today, so an "original_payment_method" refund on an
 * online payment is bookkeeping-only — staff must still action the actual
 * reversal from the gateway's own dashboard.
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
        if ($payment->status !== 'paid') {
            throw ValidationException::withMessages([
                'payment' => 'Only a paid payment can be refunded.',
            ]);
        }

        $amount = (int) $data['amount'];

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The refund amount must be greater than zero.']);
        }

        return DB::transaction(function () use ($payment, $data, $actor, $amount) {
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($amount > $locked->refundableAmount()) {
                throw ValidationException::withMessages([
                    'amount' => "This refund would exceed the refundable balance ({$locked->refundableAmount()}).",
                ]);
            }

            $refundMethod = $data['refund_method'] ?? 'original_payment_method';

            $refund = Refund::create([
                'payment_id' => $locked->id,
                'order_id' => $locked->order_id,
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'status' => 'completed',
                'refund_method' => $refundMethod,
                'processed_by_user_id' => $actor->id,
                'processed_at' => now(),
            ]);

            $locked->order->syncPaymentStatus();

            if ($locked->bill_split_part_id) {
                $this->resyncSplitPart($locked);
            }

            if ($refundMethod === 'cash') {
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
            ]);

            return $refund;
        });
    }

    private function resyncSplitPart(Payment $payment): void
    {
        $part = $payment->billSplitPart;
        $grossPaid = $part->payments()->paid()->sum('amount');
        $totalRefunded = Refund::whereIn('payment_id', $part->payments()->pluck('id'))->where('status', 'completed')->sum('amount');
        $netPaid = max(0, $grossPaid - $totalRefunded);

        $part->update([
            'amount_paid' => $netPaid,
            'status' => $netPaid >= $part->amount_due ? 'paid' : 'pending',
        ]);
    }
}
