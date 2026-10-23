<?php

namespace App\Actions;

use App\Actions\Shifts\RecordCashMovementAction;
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
        $allowed = self::TRANSITIONS[$payment->status] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move a payment from \"{$payment->status}\" to \"{$newStatus}\".",
            ]);
        }

        $fromStatus = $payment->status;

        return DB::transaction(function () use ($payment, $newStatus, $fromStatus, $actor) {
            if ($newStatus === 'paid') {
                $this->assertNoOverpayment($payment);
            }

            $payment->update([
                'status' => $newStatus,
                'paid_at' => $newStatus === 'paid' ? now() : $payment->paid_at,
            ]);

            $payment->order->syncPaymentStatus();

            $this->recordCashMovementIfApplicable($payment, $newStatus, $actor);

            $this->auditLogger->log('payment.status_changed', $payment, old: ['status' => $fromStatus], new: ['status' => $newStatus]);

            return $payment;
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
        $otherPaid = $payment->order->payments()->paid()->whereKeyNot($payment->id)->sum('amount');

        if ($otherPaid + $payment->amount > $payment->order->grand_total) {
            throw ValidationException::withMessages([
                'status' => 'Confirming this payment would exceed the order total.',
            ]);
        }
    }
}
