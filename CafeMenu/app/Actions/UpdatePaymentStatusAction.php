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

class UpdatePaymentStatusAction
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['paid', 'failed'],
        'paid' => ['refunded'],
        'failed' => [],
        'refunded' => [],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RecordCashMovementAction $recordCashMovement,
    ) {}

    public function execute(Payment $payment, string $newStatus, ?User $actor = null): Payment
    {
        return DB::transaction(function () use ($payment, $newStatus, $actor) {
            // Lock order first, then payment — the same order every payment
            // write uses (RecordPaymentAction, refunds, gateway callbacks),
            // so concurrent writers queue instead of deadlocking, and the
            // status/overpayment checks below see committed state.
            Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            /** @var Payment $locked */
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $fromStatus = $locked->status;

            if (! in_array($newStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move a payment from \"{$fromStatus}\" to \"{$newStatus}\".",
                ]);
            }

            if ($newStatus === 'paid') {
                $this->assertNoOverpayment($locked);
            }

            $locked->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : $locked->paid_at,
            ]);

            $locked->order->syncPaymentStatus();
            $locked->billSplitPart?->recalculate();

            $this->recordCashMovementIfApplicable($locked, $newStatus, $actor);

            $this->auditLogger->log('payment.status_changed', $locked, old: ['status' => $fromStatus], new: ['status' => $newStatus]);

            $payment->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /**
     * Reconciliation hook: purely additive, and a no-op whenever the actor
     * has no open shift — Shifts is an optional feature, so installs that
     * never adopt it see zero behavior change here.
     */
    private function recordCashMovementIfApplicable(Payment $payment, string $newStatus, ?User $actor): void
    {
        if ($payment->method !== 'cash' || ! $actor) {
            return;
        }

        $shift = Shift::query()->where('user_id', $actor->id)->open()->first();

        if (! $shift) {
            return;
        }

        if ($newStatus === 'paid') {
            $this->recordCashMovement->execute($shift, 'sale', $payment->amount, $actor, reference: $payment);
        } elseif ($newStatus === 'refunded') {
            $this->recordCashMovement->execute($shift, 'refund', -$payment->amount, $actor, reference: $payment);
        }
    }

    private function assertNoOverpayment(Payment $payment): void
    {
        // Net of completed refunds, the same basis RecordPaymentAction uses —
        // money that was refunded no longer counts towards the order total.
        $otherPaid = $payment->order->totalPaid();

        if ($otherPaid + $payment->amount > $payment->order->grand_total) {
            throw ValidationException::withMessages([
                'status' => 'Confirming this payment would exceed the order total.',
            ]);
        }
    }
}
