<?php

namespace App\Services\Discounts;

use App\Models\Customer;
use App\Models\Discount;
use Illuminate\Support\Carbon;

/**
 * Decides whether a Discount is eligible to apply to a given cart context.
 * Which lines it actually affects is DiscountCalculator's job — this only
 * answers yes/no for the discount as a whole.
 */
class DiscountEligibilityChecker
{
    /**
     * @param  array<int, array<string, mixed>>  $lineItems  CartPricer line items (pre-discount).
     */
    public function isEligible(Discount $discount, array $lineItems, int $subtotal, ?Customer $customer, Carbon $now): bool
    {
        if (! $discount->is_active) {
            return false;
        }

        if ($discount->campaign && ! $discount->campaign->is_active) {
            return false;
        }

        if (! $this->withinActivePeriod($discount, $now)) {
            return false;
        }

        if ($discount->min_order_amount !== null && $subtotal < $discount->min_order_amount) {
            return false;
        }

        if ($discount->min_quantity !== null) {
            $totalQuantity = array_sum(array_column($lineItems, 'quantity'));

            if ($totalQuantity < $discount->min_quantity) {
                return false;
            }
        }

        if (! $this->hasMatchingLine($discount, $lineItems)) {
            return false;
        }

        if (! $this->customerEligible($discount, $customer)) {
            return false;
        }

        return $this->withinUsageLimits($discount, $customer);
    }

    private function withinActivePeriod(Discount $discount, Carbon $now): bool
    {
        if ($discount->starts_at && $now->toDateString() < $discount->starts_at->toDateString()) {
            return false;
        }

        if ($discount->ends_at && $now->toDateString() > $discount->ends_at->toDateString()) {
            return false;
        }

        if ($discount->start_time && $discount->end_time) {
            $current = $now->format('H:i:s');

            if ($current < $discount->start_time || $current > $discount->end_time) {
                return false;
            }
        }

        if ($discount->days_of_week && ! in_array($now->dayOfWeek, $discount->days_of_week, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    private function hasMatchingLine(Discount $discount, array $lineItems): bool
    {
        if ($discount->applies_to === 'order') {
            return count($lineItems) > 0;
        }

        if ($discount->applies_to === 'specific_products') {
            $productIds = $discount->products->pluck('id')->all();

            foreach ($lineItems as $line) {
                if (in_array($line['product']->id, $productIds, true)) {
                    return true;
                }
            }

            return false;
        }

        $categoryIds = $discount->categories->pluck('id')->all();

        foreach ($lineItems as $line) {
            if (in_array($line['product']->category_id, $categoryIds, true)) {
                return true;
            }
        }

        return false;
    }

    private function customerEligible(Discount $discount, ?Customer $customer): bool
    {
        $hasCustomerRestriction = $discount->customers->isNotEmpty() || $discount->customerGroups->isNotEmpty();

        if (! $hasCustomerRestriction) {
            return true;
        }

        if (! $customer) {
            return false;
        }

        if ($discount->customers->contains('id', $customer->id)) {
            return true;
        }

        return $customer->customer_group_id !== null
            && $discount->customerGroups->contains('id', $customer->customer_group_id);
    }

    /**
     * Also called by DiscountEngine on the discount row it has just locked
     * (with $lockingRead): eligibility is first screened on an unlocked
     * copy, so two concurrent checkouts can both see a free slot — the
     * authoritative check has to be repeated once the row is locked, and
     * must read the per-customer redemption count with a locking read too,
     * since a plain read inside a REPEATABLE READ transaction can return a
     * snapshot taken before the lock was granted.
     */
    public function withinUsageLimits(Discount $discount, ?Customer $customer, bool $lockingRead = false): bool
    {
        if ($discount->usage_limit !== null && $discount->used_count >= $discount->usage_limit) {
            return false;
        }

        if ($discount->usage_limit_per_customer !== null) {
            if (! $customer) {
                return false;
            }

            $redemptions = $discount->redemptions()->where('customer_id', $customer->id);
            // Counted per order, the same unit used_count is incremented in.
            $usedByCustomer = $lockingRead
                ? $redemptions->lockForUpdate()->get(['order_id'])->unique('order_id')->count()
                : $redemptions->distinct()->count('order_id');

            if ($usedByCustomer >= $discount->usage_limit_per_customer) {
                return false;
            }
        }

        return true;
    }
}
