<?php

namespace App\Actions\Billing;

use App\Actions\RecordPaymentAction;
use App\Models\BillSplitPart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A thin wrapper around the existing RecordPaymentAction — a split payment
 * is an ordinary Payment row with bill_split_part_id set, validated against
 * the PART's remaining balance rather than the whole order's. The order's
 * own payment_status still comes from Order::syncPaymentStatus() summing
 * ALL payments regardless of split, completely unchanged.
 */
class RecordSplitPaymentAction
{
    public function __construct(
        private readonly RecordPaymentAction $recordPayment,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(BillSplitPart $part, array $data, User $actor): Payment
    {
        $amount = (int) $data['amount'];
        $status = $data['status'] ?? 'pending';

        return DB::transaction(function () use ($part, $data, $actor, $amount, $status) {
            // Order row first (the lock every payment write takes), then the
            // part — so the balance check below sees committed totals and two
            // concurrent split payments cannot both fit into the same balance.
            Order::query()->lockForUpdate()->findOrFail($part->billSplit->order_id);
            /** @var BillSplitPart $locked */
            $locked = BillSplitPart::query()->lockForUpdate()->findOrFail($part->id);

            if ($locked->status === 'paid') {
                throw ValidationException::withMessages(['part' => 'This part has already been paid in full.']);
            }

            if ($status === 'paid' && $amount > $locked->balanceDue()) {
                throw ValidationException::withMessages([
                    'amount' => "This payment would exceed this part's remaining balance ({$locked->balanceDue()}).",
                ]);
            }

            $payment = $this->recordPayment->execute($locked->billSplit->order, $data, $actor);
            $payment->update(['bill_split_part_id' => $locked->id]);

            $locked->recalculate();
            $part->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $payment->fresh();
        });
    }
}
