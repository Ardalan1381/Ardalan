<?php

namespace App\Actions\Loyalty;

use App\Models\LoyaltyRule;
use App\Models\Order;
use App\Services\Loyalty\LoyaltyLedgerService;
use Illuminate\Support\Carbon;

/**
 * Awards loyalty points for a completed order. Wired into
 * TransitionOrderStatusAction on the 'completed' transition, which is
 * terminal — this can never double-fire for the same order.
 */
class EarnLoyaltyForOrderAction
{
    public function __construct(
        private readonly LoyaltyLedgerService $ledger,
    ) {}

    public function execute(Order $order): void
    {
        if (! $order->customer_id) {
            return;
        }

        $customer = $order->customer;
        $now = Carbon::now();

        $rules = LoyaltyRule::query()
            ->active()
            ->get()
            ->filter(fn (LoyaltyRule $rule) => $this->withinActivePeriod($rule, $now));

        foreach ($rules as $rule) {
            $points = $this->pointsEarned($rule, $order);

            if ($points > 0) {
                $this->ledger->post($customer, 'earn', $points, order: $order, rule: $rule);
            }
        }
    }

    private function pointsEarned(LoyaltyRule $rule, Order $order): int
    {
        if ($rule->type === 'spend_threshold') {
            if ($rule->min_spend_amount !== null && $order->grand_total < $rule->min_spend_amount) {
                return 0;
            }

            return $rule->points_earned ?? 0;
        }

        // buy_x_get_y_points
        if (! $rule->buy_quantity || $rule->buy_quantity <= 0 || ! $rule->get_bonus_points) {
            return 0;
        }

        $totalQuantity = (int) $order->items()->sum('quantity');

        return intdiv($totalQuantity, $rule->buy_quantity) * $rule->get_bonus_points;
    }

    private function withinActivePeriod(LoyaltyRule $rule, Carbon $now): bool
    {
        if ($rule->starts_at && $now->toDateString() < $rule->starts_at->toDateString()) {
            return false;
        }

        if ($rule->ends_at && $now->toDateString() > $rule->ends_at->toDateString()) {
            return false;
        }

        return true;
    }
}
