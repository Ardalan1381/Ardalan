<?php

namespace App\Services\Discounts;

use App\Models\Discount;

/**
 * Turns one eligible Discount into concrete amounts against the current
 * cart line-item state. Mutates each affected line's running
 * 'discount_total' (always clamped so it never exceeds that line's own
 * 'line_total') and may append a new free line item. Returns the
 * discount_redemptions rows this application should record.
 */
class DiscountCalculator
{
    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<int, array{amount: int, line_index: ?int}>
     */
    public function apply(Discount $discount, array &$lineItems, int $subtotal): array
    {
        return match ($discount->type) {
            'percentage', 'fixed_amount' => $this->applyProportional($discount, $lineItems),
            'item_discount', 'half_price' => $this->applyPerLine($discount, $lineItems),
            'buy_x_get_y' => $this->applyBuyXGetY($discount, $lineItems),
            'free_item' => $this->applyFreeItem($discount, $lineItems),
        };
    }

    /**
     * percentage/fixed_amount: one amount computed against the matching
     * lines' remaining (post-earlier-discounts) total, distributed
     * proportionally across those lines, recorded as a single
     * not-tied-to-one-item redemption since it may span several lines.
     *
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return array<int, array{amount: int, line_index: ?int}>
     */
    private function applyProportional(Discount $discount, array &$lineItems): array
    {
        $matchingIndexes = $this->matchingLineIndexes($discount, $lineItems);

        if (empty($matchingIndexes)) {
            return [];
        }

        $matchingRemaining = array_sum(array_map(
            fn (int $i) => $lineItems[$i]['line_total'] - $lineItems[$i]['discount_total'],
            $matchingIndexes
        ));

        if ($matchingRemaining <= 0) {
            return [];
        }

        $rawAmount = $discount->type === 'percentage'
            ? intdiv($matchingRemaining * $discount->value, 10000)
            : $discount->value;

        $amount = min($rawAmount, $matchingRemaining);

        if ($amount <= 0) {
            return [];
        }

        $this->distributeAcrossLines($lineItems, $matchingIndexes, $amount);

        return [['amount' => $amount, 'line_index' => null]];
    }

    /**
     * item_discount/half_price: a per-line effect — every matching line
     * gets its own discount and its own redemption record.
     *
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return array<int, array{amount: int, line_index: ?int}>
     */
    private function applyPerLine(Discount $discount, array &$lineItems): array
    {
        $redemptions = [];

        foreach ($this->matchingLineIndexes($discount, $lineItems) as $index) {
            $remaining = $lineItems[$index]['line_total'] - $lineItems[$index]['discount_total'];

            if ($remaining <= 0) {
                continue;
            }

            $rawAmount = $discount->type === 'half_price'
                ? intdiv($lineItems[$index]['line_total'], 2)
                : $discount->value;

            $amount = min($rawAmount, $remaining);

            if ($amount <= 0) {
                continue;
            }

            $lineItems[$index]['discount_total'] += $amount;
            $redemptions[] = ['amount' => $amount, 'line_index' => $index];
        }

        return $redemptions;
    }

    /**
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return array<int, array{amount: int, line_index: ?int}>
     */
    private function applyBuyXGetY(Discount $discount, array &$lineItems): array
    {
        $matchingIndexes = $this->matchingLineIndexes($discount, $lineItems);
        $buy = $discount->buy_quantity ?? 0;
        $get = $discount->get_quantity ?? 0;

        if ($buy <= 0 || $get <= 0 || empty($matchingIndexes)) {
            return [];
        }

        $units = [];

        foreach ($matchingIndexes as $index) {
            $line = $lineItems[$index];
            $perUnit = intdiv($line['line_total'], $line['quantity']);

            for ($i = 0; $i < $line['quantity']; $i++) {
                $units[] = ['line_index' => $index, 'unit_price' => $perUnit];
            }
        }

        $freeUnitCount = intdiv(count($units), $buy + $get) * $get;

        if ($freeUnitCount <= 0) {
            return [];
        }

        // Discount the cheapest qualifying units first — the conservative,
        // deterministic interpretation of "every Nth unit free" once units
        // differ in price (e.g. mixed add-ons across matching lines).
        usort($units, fn ($a, $b) => $a['unit_price'] <=> $b['unit_price']);
        $freeUnits = array_slice($units, 0, $freeUnitCount);

        $perLineFree = [];
        foreach ($freeUnits as $unit) {
            $perLineFree[$unit['line_index']] = ($perLineFree[$unit['line_index']] ?? 0) + $unit['unit_price'];
        }

        $totalAmount = 0;
        foreach ($perLineFree as $index => $amount) {
            $remaining = $lineItems[$index]['line_total'] - $lineItems[$index]['discount_total'];
            $amount = min($amount, $remaining);

            if ($amount <= 0) {
                continue;
            }

            $lineItems[$index]['discount_total'] += $amount;
            $totalAmount += $amount;
        }

        return $totalAmount > 0 ? [['amount' => $totalAmount, 'line_index' => null]] : [];
    }

    /**
     * Adds a real order_item for the free product at its normal price, then
     * fully discounts that line — inventory consumption and sales reporting
     * both see the item as genuinely given away, not invented.
     *
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @return array<int, array{amount: int, line_index: ?int}>
     */
    private function applyFreeItem(Discount $discount, array &$lineItems): array
    {
        $product = $discount->freeItemProduct;

        if (! $product || ! $product->is_active || ! $product->is_available) {
            return [];
        }

        $quantity = $discount->get_quantity ?? 1;
        $unitPrice = $product->effectivePrice();
        $lineTotal = $unitPrice * $quantity;

        $lineItems[] = [
            'product' => $product,
            'options' => collect(),
            'quantity' => $quantity,
            'notes' => null,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'discount_total' => $lineTotal,
        ];

        return [['amount' => $lineTotal, 'line_index' => array_key_last($lineItems)]];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return list<int>
     */
    private function matchingLineIndexes(Discount $discount, array $lineItems): array
    {
        if ($discount->applies_to === 'order') {
            return array_keys($lineItems);
        }

        if ($discount->applies_to === 'specific_products') {
            $productIds = $discount->products->pluck('id')->all();

            return array_keys(array_filter(
                $lineItems,
                fn (array $line) => in_array($line['product']->id, $productIds, true)
            ));
        }

        $categoryIds = $discount->categories->pluck('id')->all();

        return array_keys(array_filter(
            $lineItems,
            fn (array $line) => in_array($line['product']->category_id, $categoryIds, true)
        ));
    }

    /**
     * Splits $totalAmount across $indexes in proportion to each line's
     * remaining (undiscounted) balance, using a largest-remainder close-out
     * on the last line so the parts always sum exactly to $totalAmount
     * despite integer-division rounding.
     *
     * @param  array<int, array<string, mixed>>  &$lineItems
     * @param  list<int>  $indexes
     */
    private function distributeAcrossLines(array &$lineItems, array $indexes, int $totalAmount): void
    {
        $remainingPerLine = [];

        foreach ($indexes as $index) {
            $remainingPerLine[$index] = $lineItems[$index]['line_total'] - $lineItems[$index]['discount_total'];
        }

        $totalRemaining = array_sum($remainingPerLine);

        if ($totalRemaining <= 0) {
            return;
        }

        $distributed = 0;
        $count = count($indexes);
        $i = 0;

        foreach ($indexes as $index) {
            $i++;
            $share = $i === $count
                ? $totalAmount - $distributed
                : intdiv($totalAmount * $remainingPerLine[$index], $totalRemaining);

            $share = max(0, min($share, $remainingPerLine[$index]));
            $lineItems[$index]['discount_total'] += $share;
            $distributed += $share;
        }
    }
}
