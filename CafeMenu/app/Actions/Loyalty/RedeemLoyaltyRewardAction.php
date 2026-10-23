<?php

namespace App\Actions\Loyalty;

use App\Models\Customer;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyReward;
use App\Models\User;
use App\Services\Loyalty\LoyaltyLedgerService;
use Illuminate\Validation\ValidationException;

/**
 * Converts loyalty points into a discount effect on an in-progress cart —
 * points are never money, so redemption always targets a specific order's
 * line items rather than a spendable wallet. Mirrors DiscountCalculator's
 * contract (mutates $lineItems, clamped to each line's remaining balance)
 * so a POS/staff order-creation flow can call this the same way it calls
 * the Discount Engine, before the Order row exists.
 *
 * Deducts points immediately (inside the caller's own transaction) so the
 * balance and the cart total are always consistent, even though the
 * returned ledger entry's order_id can only be backfilled once the Order
 * this redemption belongs to has actually been created.
 */
class RedeemLoyaltyRewardAction
{
    public function __construct(
        private readonly LoyaltyLedgerService $ledger,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return array{amount: int, line_index: ?int, points_spent: int, ledger_entry: LoyaltyLedgerEntry}
     */
    public function execute(Customer $customer, LoyaltyReward $reward, array &$lineItems, ?User $actor = null): array
    {
        if (! $reward->is_active) {
            throw ValidationException::withMessages(['reward' => 'This reward is no longer available.']);
        }

        if ($customer->loyalty_points_balance < $reward->points_cost) {
            throw ValidationException::withMessages(['reward' => 'This customer does not have enough loyalty points for this reward.']);
        }

        $effect = match ($reward->reward_type) {
            'fixed_discount_amount' => $this->applyOrderWideAmount($lineItems, $reward->value),
            'percentage_discount' => $this->applyOrderWidePercentage($lineItems, $reward->value),
            'free_product' => $this->applyFreeProduct($lineItems, $reward),
        };

        if ($effect === null) {
            throw ValidationException::withMessages(['reward' => 'This reward cannot be applied to the current cart.']);
        }

        $ledgerEntry = $this->ledger->post(
            $customer,
            'redeem',
            -$reward->points_cost,
            reward: $reward,
            actor: $actor,
        );

        return [
            'amount' => $effect['amount'],
            'line_index' => $effect['line_index'],
            'points_spent' => $reward->points_cost,
            'ledger_entry' => $ledgerEntry,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return ?array{amount: int, line_index: ?int}
     */
    private function applyOrderWideAmount(array &$lineItems, ?int $value): ?array
    {
        if (! $value) {
            return null;
        }

        $remainingPerLine = $this->remainingPerLine($lineItems);
        $totalRemaining = array_sum($remainingPerLine);

        if ($totalRemaining <= 0) {
            return null;
        }

        $amount = min($value, $totalRemaining);
        $this->distribute($lineItems, $remainingPerLine, $amount);

        return ['amount' => $amount, 'line_index' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return ?array{amount: int, line_index: ?int}
     */
    private function applyOrderWidePercentage(array &$lineItems, ?int $bps): ?array
    {
        if (! $bps) {
            return null;
        }

        $remainingPerLine = $this->remainingPerLine($lineItems);
        $totalRemaining = array_sum($remainingPerLine);

        if ($totalRemaining <= 0) {
            return null;
        }

        $amount = min(intdiv($totalRemaining * $bps, 10000), $totalRemaining);

        if ($amount <= 0) {
            return null;
        }

        $this->distribute($lineItems, $remainingPerLine, $amount);

        return ['amount' => $amount, 'line_index' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return ?array{amount: int, line_index: ?int}
     */
    private function applyFreeProduct(array &$lineItems, LoyaltyReward $reward): ?array
    {
        $product = $reward->freeProduct;

        if (! $product || ! $product->is_active || ! $product->is_available) {
            return null;
        }

        $unitPrice = $product->effectivePrice();

        $lineItems[] = [
            'product' => $product,
            'options' => collect(),
            'quantity' => 1,
            'notes' => null,
            'unit_price' => $unitPrice,
            'line_total' => $unitPrice,
            'discount_total' => $unitPrice,
        ];

        return ['amount' => $unitPrice, 'line_index' => array_key_last($lineItems)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, int>
     */
    private function remainingPerLine(array $lineItems): array
    {
        $remaining = [];

        foreach ($lineItems as $index => $line) {
            $remaining[$index] = $line['line_total'] - ($line['discount_total'] ?? 0);
        }

        return $remaining;
    }

    /**
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @param  array<int, int>  $remainingPerLine
     */
    private function distribute(array &$lineItems, array $remainingPerLine, int $totalAmount): void
    {
        $totalRemaining = array_sum($remainingPerLine);
        $distributed = 0;
        $count = count($remainingPerLine);
        $i = 0;

        foreach ($remainingPerLine as $index => $remaining) {
            $i++;
            $share = $i === $count
                ? $totalAmount - $distributed
                : intdiv($totalAmount * $remaining, $totalRemaining);

            $share = max(0, min($share, $remaining));
            $lineItems[$index]['discount_total'] = ($lineItems[$index]['discount_total'] ?? 0) + $share;
            $distributed += $share;
        }
    }
}
