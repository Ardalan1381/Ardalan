<?php

namespace App\Services\Discounts;

use App\Models\Customer;
use App\Models\Discount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Orchestrates which Discounts apply to a priced cart: picks eligible
 * winners (at most one non-stackable, plus any number of stackable ones),
 * applies them in priority order, and reserves their usage-limit slots.
 * Runs inside the caller's own DB transaction (CartPricer's callers already
 * wrap cart pricing in one) so the used_count increments below are atomic
 * with the order they belong to.
 */
class DiscountEngine
{
    public function __construct(
        private readonly DiscountEligibilityChecker $eligibilityChecker,
        private readonly DiscountCalculator $calculator,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array{line_items: array<int, array<string, mixed>>, discount_total: int, redemptions: array<int, array{discount: Discount, amount: int, line_index: ?int}>}
     */
    public function apply(array $lineItems, int $subtotal, ?Customer $customer): array
    {
        foreach ($lineItems as &$line) {
            $line['discount_total'] = 0;
        }
        unset($line);

        $now = Carbon::now();

        $candidates = Discount::query()
            ->active()
            ->with(['products', 'categories', 'customers', 'customerGroups', 'campaign'])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (Discount $discount) => $this->eligibilityChecker->isEligible($discount, $lineItems, $subtotal, $customer, $now));

        $redemptions = [];

        foreach ($this->pickWinners($candidates) as $discount) {
            /** @var Discount $locked */
            $locked = Discount::query()->lockForUpdate()->find($discount->id);

            if (! $locked || ! $locked->is_active || ! $this->eligibilityChecker->withinUsageLimits($locked, $customer, lockingRead: true)) {
                continue;
            }

            $locked->setRelations($discount->getRelations());

            $applied = $this->calculator->apply($locked, $lineItems, $subtotal);

            if (empty($applied)) {
                continue;
            }

            $locked->increment('used_count');

            foreach ($applied as $effect) {
                $redemptions[] = [
                    'discount' => $locked,
                    'amount' => $effect['amount'],
                    'line_index' => $effect['line_index'],
                ];
            }
        }

        return [
            'line_items' => $lineItems,
            'discount_total' => array_sum(array_column($lineItems, 'discount_total')),
            'redemptions' => $redemptions,
        ];
    }

    /**
     * @param  Collection<int, Discount>  $candidates
     * @return Collection<int, Discount>
     */
    private function pickWinners(Collection $candidates): Collection
    {
        $nonStackable = $candidates->first(fn (Discount $discount) => ! $discount->is_stackable);
        $stackable = $candidates->filter(fn (Discount $discount) => $discount->is_stackable)->values();

        return $nonStackable ? $stackable->push($nonStackable)->sortByDesc('priority')->values() : $stackable;
    }
}
