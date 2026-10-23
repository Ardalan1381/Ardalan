<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes out a 'pending' refund once staff know what happened outside the
 * application: 'completed' when the money was actually returned (card
 * reversal done, gateway refund issued, store credit handed over) — only
 * then does it reduce what the order counts as paid — or 'failed' when it
 * was not, which releases the reserved amount so it can be refunded again.
 * Nothing here talks to a payment gateway; it records a human confirmation.
 */
class ResolveRefundAction
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  'completed'|'failed'  $outcome
     */
    public function execute(Refund $refund, string $outcome, User $actor, ?string $externalReference = null, ?string $note = null): Refund
    {
        if (! in_array($outcome, ['completed', 'failed'], true)) {
            throw ValidationException::withMessages(['outcome' => 'Unknown refund outcome.']);
        }

        return DB::transaction(function () use ($refund, $outcome, $actor, $externalReference, $note) {
            Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            Payment::query()->lockForUpdate()->findOrFail($refund->payment_id);
            /** @var Refund $locked */
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);

            if (! $locked->isPending()) {
                throw ValidationException::withMessages(['refund' => 'Only a pending refund can be confirmed or marked as failed.']);
            }

            $locked->update([
                'status' => $outcome,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => now(),
                'external_reference' => $externalReference,
                'resolution_note' => $note,
            ]);

            $locked->order->syncPaymentStatus();
            $locked->payment->billSplitPart?->recalculate();

            $this->auditLogger->log($outcome === 'completed' ? 'refund.confirmed' : 'refund.failed', $locked, old: ['status' => 'pending'], new: [
                'status' => $outcome,
                'external_reference' => $externalReference,
            ]);

            return $locked;
        });
    }
}
