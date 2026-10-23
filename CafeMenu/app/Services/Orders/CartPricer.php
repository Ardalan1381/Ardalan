<?php

namespace App\Services\Orders;

use App\Actions\Loyalty\RedeemLoyaltyRewardAction;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\LoyaltyReward;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\User;
use App\Services\Discounts\DiscountEngine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Re-resolves and prices a client-submitted cart against the database
 * inside the caller's own transaction, then runs the Discount Engine
 * against the result. Shared by the public checkout flow and any
 * staff-facing order creation, so both paths enforce identical pricing,
 * availability, and discount rules — the client is never trusted for
 * prices, names, or availability.
 */
class CartPricer
{
    public function __construct(
        private readonly DiscountEngine $discountEngine,
        private readonly RedeemLoyaltyRewardAction $redeemLoyaltyReward,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{line_items: array<int, array<string, mixed>>, subtotal: int, discount_total: int, tax_total: int, service_charge_total: int, grand_total: int, discount_redemptions: array<int, array<string, mixed>>, loyalty_redemption: ?array<string, mixed>}
     */
    public function price(
        array $items,
        BusinessSetting $business,
        ?Customer $customer = null,
        ?LoyaltyReward $redeemReward = null,
        ?User $actor = null,
    ): array {
        $subtotal = 0;
        $lineItems = [];

        foreach ($items as $item) {
            $product = Product::query()->with('category')->lockForUpdate()->find($item['product_id']);

            if (! $product || ! $product->is_active || ! $product->is_available) {
                throw ValidationException::withMessages([
                    'items' => "\"{$product?->name}\" is no longer available.",
                ]);
            }

            if (! $product->category || ! $product->category->is_active) {
                throw ValidationException::withMessages([
                    'items' => "\"{$product->name}\" is no longer available.",
                ]);
            }

            $optionIds = $item['option_ids'] ?? [];
            $options = ProductOption::query()
                ->whereIn('id', $optionIds)
                ->where('is_active', true)
                ->whereHas('group', fn ($query) => $query->where('product_id', $product->id)->where('is_active', true))
                ->get();

            if ($options->count() !== count($optionIds)) {
                throw ValidationException::withMessages([
                    'items' => "One or more selected options for \"{$product->name}\" are no longer available.",
                ]);
            }

            $this->assertOptionSelectionsValid($product, $options);

            $unitPrice = $product->effectivePrice();
            $lineUnitTotal = $unitPrice + $options->sum('price_delta');
            $quantity = (int) $item['quantity'];
            $lineTotal = $lineUnitTotal * $quantity;
            $subtotal += $lineTotal;

            $lineItems[] = [
                'product' => $product,
                'options' => $options,
                'quantity' => $quantity,
                'notes' => $item['notes'] ?? null,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        $discounted = $this->discountEngine->apply($lineItems, $subtotal, $customer);
        $lineItems = $discounted['line_items'];
        $discountTotal = $discounted['discount_total'];

        $loyaltyRedemption = null;

        if ($redeemReward) {
            if (! $customer) {
                throw ValidationException::withMessages(['reward' => 'A customer must be selected to redeem a loyalty reward.']);
            }

            $redeemed = $this->redeemLoyaltyReward->execute($customer, $redeemReward, $lineItems, $actor);
            $discountTotal += $redeemed['amount'];
            $loyaltyRedemption = $redeemed;
        }

        // Recompute the gross subtotal from the final line set — the
        // Discount Engine (and a free-product loyalty reward) may have
        // appended lines that weren't part of the original cart.
        $subtotal = array_sum(array_column($lineItems, 'line_total'));
        $postDiscountSubtotal = $subtotal - $discountTotal;

        $taxTotal = $business->tax_enabled ? intdiv($postDiscountSubtotal * $business->tax_rate_bps, 10000) : 0;
        $serviceChargeTotal = $business->service_charge_enabled ? intdiv($postDiscountSubtotal * $business->service_charge_rate_bps, 10000) : 0;

        return [
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'service_charge_total' => $serviceChargeTotal,
            'grand_total' => $postDiscountSubtotal + $taxTotal + $serviceChargeTotal,
            'discount_redemptions' => $discounted['redemptions'],
            'loyalty_redemption' => $loyaltyRedemption,
        ];
    }

    private function assertOptionSelectionsValid(Product $product, Collection $selectedOptions): void
    {
        $groups = $product->optionGroups()->where('is_active', true)->get();

        foreach ($groups as $group) {
            $selectedInGroup = $selectedOptions->where('product_option_group_id', $group->id)->count();

            if ($selectedInGroup < $group->min_select) {
                throw ValidationException::withMessages([
                    'items' => "\"{$group->name}\" on \"{$product->name}\" requires at least {$group->min_select} selection(s).",
                ]);
            }

            if ($group->max_select !== null && $selectedInGroup > $group->max_select) {
                throw ValidationException::withMessages([
                    'items' => "\"{$group->name}\" on \"{$product->name}\" allows at most {$group->max_select} selection(s).",
                ]);
            }
        }
    }
}
