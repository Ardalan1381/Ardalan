<?php

namespace App\Services\Loyalty;

use App\Models\Customer;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The single choke point for every points balance change — mirrors
 * StockMovementService's role for inventory. No caller ever writes
 * Customer::loyalty_points_balance directly; every change is a traceable
 * ledger entry, and a balance can never go negative.
 */
class LoyaltyLedgerService
{
    public function post(
        Customer $customer,
        string $type,
        int $points,
        ?Order $order = null,
        ?LoyaltyRule $rule = null,
        ?LoyaltyReward $reward = null,
        ?string $notes = null,
        ?User $actor = null,
    ): LoyaltyLedgerEntry {
        /** @var Customer $locked */
        $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);

        $resultingBalance = $locked->loyalty_points_balance + $points;

        if ($resultingBalance < 0) {
            throw ValidationException::withMessages([
                'points' => 'This customer does not have enough loyalty points.',
            ]);
        }

        $entry = LoyaltyLedgerEntry::create([
            'customer_id' => $locked->id,
            'type' => $type,
            'points' => $points,
            'resulting_balance' => $resultingBalance,
            'order_id' => $order?->id,
            'loyalty_rule_id' => $rule?->id,
            'loyalty_reward_id' => $reward?->id,
            'notes' => $notes,
            'created_by_user_id' => $actor?->id,
        ]);

        // Direct assignment + save(), not update() — loyalty_points_balance
        // is deliberately excluded from $fillable (like Order::customer_id's
        // siblings), so mass-assignment would silently discard it here too.
        $locked->loyalty_points_balance = $resultingBalance;
        $locked->save();
        $customer->setAttribute('loyalty_points_balance', $resultingBalance);

        return $entry;
    }
}
