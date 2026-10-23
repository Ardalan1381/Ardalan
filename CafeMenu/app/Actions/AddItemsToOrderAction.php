<?php

namespace App\Actions;

use App\Models\BusinessSetting;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\LoyaltyRedemption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Inventory\RecipeConsumptionService;
use App\Services\Orders\CartPricer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Appends items to a still-in-flight dine-in order.
 *
 * The ENTIRE cart (existing items + the newly added ones) is re-priced
 * through the same CartPricer used at order creation — the only way to keep
 * discount eligibility (min-quantity thresholds, buy-X-get-Y groupings,
 * etc.) correct once the cart composition changes.
 *
 * Existing order_items rows are never deleted and re-created: they keep
 * their ids (kitchen tickets, stock movements and invoices point at them)
 * and only their priced columns are updated. New lines get new rows. If
 * the order's stock was already consumed (confirmed/preparing), only the
 * new rows are consumed now — exactly once.
 *
 * Rules by order status: pending, confirmed and preparing accept new items;
 * ready, completed, cancelled and rejected do not. Orders with a redeemed
 * loyalty reward, an issued invoice or a bill split are also refused, since
 * each of those has fixed the order's totals.
 */
class AddItemsToOrderAction
{
    private const EDITABLE_STATUSES = ['pending', 'confirmed', 'preparing'];

    public function __construct(
        private readonly CartPricer $cartPricer,
        private readonly RecipeConsumptionService $recipeConsumption,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $newItems
     */
    public function execute(Order $order, array $newItems, User $actor): Order
    {
        return DB::transaction(function () use ($order, $newItems, $actor) {
            // Locked for the whole edit: a concurrent status transition
            // (e.g. confirm → consume stock) must see either the order
            // before the new items or after them, never half of it.
            /** @var Order $order */
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            $this->assertEditable($order);

            $business = BusinessSetting::current();

            // Lines the Discount Engine created itself (a free_item reward)
            // are not part of what the customer asked for: re-submitting
            // them as ordinary cart lines would bill the free product.
            $engineItemIds = DiscountRedemption::query()
                ->where('order_id', $order->id)
                ->where('discount_type_snapshot', 'free_item')
                ->whereNotNull('order_item_id')
                ->pluck('order_item_id')
                ->all();

            $existingItems = $order->items()->with('options')->orderBy('id')->get();
            $customerItems = $existingItems
                ->filter(fn (OrderItem $item) => $item->product_id !== null && ! in_array($item->id, $engineItemIds, true))
                ->values();
            $engineItems = $existingItems->filter(fn (OrderItem $item) => in_array($item->id, $engineItemIds, true))->values();

            $combinedItems = [
                ...$customerItems->map(fn (OrderItem $item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'notes' => $item->item_notes,
                    'option_ids' => $item->options->pluck('product_option_id')->filter()->values()->all(),
                ])->all(),
                ...$newItems,
            ];

            // Give back the usage-limit slot each currently-applied discount
            // holds before re-pricing — otherwise every edit would
            // permanently inflate used_count for a discount that was
            // already counted once for this same order.
            $previousDiscountIds = DiscountRedemption::where('order_id', $order->id)->distinct()->pluck('discount_id');
            foreach ($previousDiscountIds as $discountId) {
                Discount::query()->whereKey($discountId)->lockForUpdate()->decrement('used_count');
            }

            $priced = $this->cartPricer->price($combinedItems, $business, $order->customer);

            DiscountRedemption::where('order_id', $order->id)->delete();

            $rowsByLineIndex = $this->persistLines($order, $priced['line_items'], $customerItems, $engineItems);

            foreach ($priced['discount_redemptions'] as $redemption) {
                DiscountRedemption::create([
                    'discount_id' => $redemption['discount']->id,
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'order_item_id' => $redemption['line_index'] !== null
                        ? $rowsByLineIndex[$redemption['line_index']]->id
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

            // Stock for the order has already been taken: take it for the
            // new rows too (consume() skips rows already consumed).
            if ($order->inventory_consumed_at) {
                $this->recipeConsumption->consume($order);
            }

            $order->statusHistory()->create([
                'from_status' => $order->status,
                'to_status' => $order->status,
                'changed_by_user_id' => $actor->id,
                'note' => 'Items added to order.',
            ]);

            return $order->fresh(['items.options']);
        });
    }

    private function assertEditable(Order $order): void
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

        // The previous implementation silently dropped lines whose product
        // had since been deleted (they cannot be re-priced). Refuse instead:
        // that line was ordered and may already have been served.
        if ($order->items()->whereNull('product_id')->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Cannot add items: this order contains a product that has since been deleted. Start a new order for the extra items.',
            ]);
        }

        // A split divides the current grand total between its parts; a
        // larger total would leave money no part is responsible for.
        if ($order->billSplits()->exists()) {
            throw ValidationException::withMessages([
                'order' => 'Cannot add items after the bill has been split.',
            ]);
        }
    }

    /**
     * Maps priced lines onto rows: the first lines are the existing customer
     * items (same order, so same rows), then the new items (new rows), then
     * any lines the Discount Engine appended (re-using an existing
     * engine-created row for the same product where there is one).
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<int, OrderItem>  $customerItems
     * @param  Collection<int, OrderItem>  $engineItems
     * @return array<int, OrderItem>
     */
    private function persistLines(Order $order, array $lines, Collection $customerItems, Collection $engineItems): array
    {
        $rows = [];
        $unusedEngineItems = $engineItems->keyBy('id');

        foreach ($lines as $index => $line) {
            $existing = $customerItems->get($index);

            if (! $existing && $index >= $customerItems->count() && ! empty($line['engine_line'] ?? false)) {
                $existing = $unusedEngineItems->first(fn (OrderItem $item) => $item->product_id === $line['product']->id);
                if ($existing) {
                    $unusedEngineItems->forget($existing->id);
                }
            }

            if ($existing) {
                $existing->update([
                    'unit_price_snapshot' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'discount_total' => $line['discount_total'] ?? 0,
                ]);
                $rows[$index] = $existing;

                continue;
            }

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

            foreach ($line['options'] as $option) {
                $orderItem->options()->create([
                    'product_option_id' => $option->id,
                    'option_name_snapshot' => $option->name,
                    'price_delta_snapshot' => $option->price_delta,
                ]);
            }

            $rows[$index] = $orderItem;
        }

        // A free item the order used to get and no longer qualifies for.
        // Silently deleting a line the kitchen may already have made (and
        // whose stock was taken) is not acceptable, so the edit is refused.
        if ($unusedEngineItems->isNotEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'Adding these items would remove a free item already on this order. Cancel and re-place the order instead.',
            ]);
        }

        return $rows;
    }
}
