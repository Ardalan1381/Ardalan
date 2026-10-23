<?php

namespace App\Actions\Billing;

use App\Actions\RecordPaymentAction;
use App\Models\BillSplitPart;
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
        if ($part->status === 'paid') {
            throw ValidationException::withMessages(['part' => 'This part has already been paid in full.']);
        }

        $amount = (int) $data['amount'];
        $status = $data['status'] ?? 'pending';

        if ($status === 'paid' && $amount > $part->balanceDue()) {
            throw ValidationException::withMessages([
                'amount' => "This payment would exceed this part's remaining balance ({$part->balanceDue()}).",
            ]);
        }

        return DB::transaction(function () use ($part, $data, $actor, $amount, $status) {
            $payment = $this->recordPayment->execute($part->billSplit->order, $data, $actor);
            $payment->update(['bill_split_part_id' => $part->id]);

            if ($status === 'paid') {
                $newAmountPaid = $part->amount_paid + $amount;
                $part->update([
                    'amount_paid' => $newAmountPaid,
                    'status' => $newAmountPaid >= $part->amount_due ? 'paid' : 'pending',
                ]);

                $billSplit = $part->billSplit;

                if ($billSplit->parts()->where('status', '!=', 'paid')->doesntExist()) {
                    $billSplit->update(['status' => 'settled']);
                }
            }

            return $payment->fresh();
        });
    }
}
