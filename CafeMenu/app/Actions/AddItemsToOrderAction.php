<?php

namespace App\Actions;

use App\Models\BusinessSetting;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\LoyaltyRedemption;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\CartPricer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Appends items to a still-in-flight dine-in order. Rather than patching
 * the existing rows incrementally, the ENTIRE cart (existing items + the
 * newly added ones) is re-priced from scratch through the same CartPricer
 * used at order creation — this is the only way to keep discount
 * eligibility (min-quantity thresholds, buy-X-get-Y groupings, etc.)
 * correct once the cart composition changes, rather than accumulating
 * drift from patching totals in place.
 */
class AddItemsToOrderAction
{
    private const EDITABLE_STATUSES = ['pending', 'confirmed', 'preparing'];

    public function __construct(
        private readonly CartPricer $cartPricer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $newItems
     */
    public function execute(Order $order, array $newItems, User $actor): Order
    {
        if ($order->type !== 'dine_in' || ! $order->tableSession || $order->tableSession->status !== 'open') {
            throw ValidationException::withMessages([
                'order' => 'Items can only be added to a dine-in order with an open table session.',
            ]);
        }

        if (! in_array($order->status, self::EDITABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'order' => "Cannot add items to an order that is already \"{$order->status}\".",
            ]);
        }

        // A redeemed loyalty reward already deducted real points from the
        // customer's balance — re-pricing from scratch without re-asking
        // for that reward would silently drop it and lose those points, so
        // this is refused outright rather than risking that.
        if (LoyaltyRedemption::where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Cannot add items after a loyalty reward has been redeemed on this order.',
            ]);
        }

        // An invoice is a financial document that must never change once
        // issued — refuse to edit an order after one has been generated
        // for it, rather than silently invalidating the invoice's totals.
        if ($order->invoice()->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Cannot add items after an invoice has been generated for this order.',
            ]);
        }

        $business = BusinessSetting::current();
        $customer = $order->customer;

        $existingItems = $order->items()->with('options')->get()
            ->filter(fn ($item) => $item->product_id !== null)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'notes' => $item->item_notes,
                'option_ids' => $item->options->pluck('product_option_id')->filter()->values()->all(),
            ])->values()->all();

        $combinedItems = [...$existingItems, ...$newItems];

        return DB::transaction(function () use ($order, $combinedItems, $business, $customer, $actor) {
            // Give back the usage-limit slot each currently-applied discount
            // holds before re-pricing — otherwise every edit would
            // permanently inflate used_count for a discount that was
            // already counted once for this same order.
            $previousDiscountIds = DiscountRedemption::where('order_id', $order->id)->distinct()->pluck('discount_id');
            foreach ($previousDiscountIds as $discountId) {
                Discount::query()->whereKey($discountId)->lockForUpdate()->decrement('used_count');
            }

            $priced = $this->cartPricer->price($combinedItems, $business, $customer);

            DiscountRedemption::where('order_id', $order->id)->delete();
            $order->items()->delete();

            $createdItemsByLineIndex = [];

            foreach ($priced['line_items'] as $lineIndex => $line) {
                $orderItem = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name_snapshot' => $line['product']->name,
                    'product_description_snapshot' => $line['product']->description,
                    'unit_price_snapshot' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'discount_total' => $line['discount_total'] ?? 0,
                    'item_notes' => $line['notes'],
                ]);

                $createdItemsByLineIndex[$lineIndex] = $orderItem;

                foreach ($line['options'] as $option) {
                    $orderItem->options()->create([
                        'product_option_id' => $option->id,
                        'option_name_snapshot' => $option->name,
                        'price_delta_snapshot' => $option->price_delta,
                    ]);
                }
            }

            foreach ($priced['discount_redemptions'] as $redemption) {
                DiscountRedemption::create([
                    'discount_id' => $redemption['discount']->id,
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'order_item_id' => $redemption['line_index'] !== null
                        ? $createdItemsByLineIndex[$redemption['line_index']]->id
                        : null,
                    'amount_discounted' => $redemption['amount'],
                    'discount_name_snapshot' => $redemption['discount']->name,
                    'discount_type_snapshot' => $redemption['discount']->type,
                ]);
            }

            $order->update([
                'subtotal' => $priced['subtotal'],
                'discount_total' => $priced['discount_total'],
                'tax_total' => $priced['tax_total'],
                'service_charge_total' => $priced['service_charge_total'],
                'grand_total' => $priced['grand_total'],
            ]);

            $order->statusHistory()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by_user_id' => $actor->id,
                'note' => 'Items added to order.',
            ]);

            return $order->fresh(['items.options']);
        });
    }
}
