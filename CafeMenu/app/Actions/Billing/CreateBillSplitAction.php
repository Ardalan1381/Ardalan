<?php

namespace App\Actions\Billing;

use App\Models\BillSplit;
use App\Models\BillSplitPartItem;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Splits an order's total into multiple parts. The double-charge guard for
 * by_item splits is enforced twice: here (for a friendly validation error)
 * and, unconditionally, by a UNIQUE constraint on
 * bill_split_part_items.order_item_id — so even a bug here can never let
 * one order_item end up billed through two different parts.
 */
class CreateBillSplitAction
{
    /**
     * @param  array<int, array{label: string, order_item_ids?: array<int, int>, amount?: int}>  $parts
     */
    public function execute(Order $order, string $splitType, array $parts, User $actor): BillSplit
    {
        if ($order->billSplits()->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['order' => 'This order already has an open split.']);
        }

        if ($order->payments()->whereNull('bill_split_part_id')->where('status', 'paid')->exists()) {
            throw ValidationException::withMessages(['order' => 'Cannot split an order that already has a whole-order payment.']);
        }

        $amounts = match ($splitType) {
            'by_item' => $this->computeByItemAmounts($order, $parts),
            'by_amount' => $this->computeByAmountAmounts($order, $parts),
            'even' => $this->distributeProportionally(array_fill(0, count($parts), 1), $order->grand_total),
            default => throw ValidationException::withMessages(['split_type' => 'Unknown split type.']),
        };

        return DB::transaction(function () use ($order, $splitType, $parts, $amounts, $actor) {
            $billSplit = BillSplit::create([
                'order_id' => $order->id,
                'split_type' => $splitType,
                'status' => 'open',
                'created_by_user_id' => $actor->id,
            ]);

            foreach ($parts as $index => $partSpec) {
                $part = $billSplit->parts()->create([
                    'label' => $partSpec['label'],
                    'amount_due' => $amounts[$index],
                    'status' => 'pending',
                ]);

                if ($splitType === 'by_item') {
                    foreach ($partSpec['order_item_ids'] as $orderItemId) {
                        $orderItem = $order->items()->findOrFail($orderItemId);

                        if (BillSplitPartItem::where('order_item_id', $orderItemId)->exists()) {
                            throw ValidationException::withMessages([
                                'order_item_ids' => 'One or more items are already part of a split.',
                            ]);
                        }

                        $part->items()->create([
                            'order_item_id' => $orderItemId,
                            'quantity_allocated' => $orderItem->quantity,
                        ]);
                    }
                }
            }

            return $billSplit->fresh('parts.items');
        });
    }

    /**
     * @param  array<int, array{order_item_ids: array<int, int>}>  $parts
     * @return array<int, int>
     */
    private function computeByItemAmounts(Order $order, array $parts): array
    {
        $allIds = collect($parts)->flatMap(fn (array $p) => $p['order_item_ids'])->all();

        if (count($allIds) !== count(array_unique($allIds))) {
            throw ValidationException::withMessages(['order_item_ids' => 'Each order item can only be assigned to one part.']);
        }

        $orderItems = $order->items()->whereIn('id', $allIds)->get()->keyBy('id');

        if ($orderItems->count() !== count($allIds)) {
            throw ValidationException::withMessages(['order_item_ids' => 'One or more items do not belong to this order.']);
        }

        $netWeights = [];

        foreach ($parts as $index => $partSpec) {
            $net = 0;

            foreach ($partSpec['order_item_ids'] as $id) {
                $item = $orderItems[$id];
                $net += $item->line_total - $item->discount_total;
            }

            $netWeights[$index] = max(0, $net);
        }

        return $this->distributeProportionally($netWeights, $order->grand_total);
    }

    /**
     * @param  array<int, array{amount: int}>  $parts
     * @return array<int, int>
     */
    private function computeByAmountAmounts(Order $order, array $parts): array
    {
        $amounts = [];
        $total = 0;

        foreach ($parts as $index => $partSpec) {
            $amounts[$index] = (int) $partSpec['amount'];
            $total += $amounts[$index];
        }

        if ($total !== $order->grand_total) {
            throw ValidationException::withMessages([
                'amount' => "Split amounts must sum exactly to the order total ({$order->grand_total}).",
            ]);
        }

        return $amounts;
    }

    /**
     * Splits $total across the given weights, using a largest-remainder
     * close-out on the last entry so the parts always sum exactly to
     * $total despite integer-division rounding.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function distributeProportionally(array $weights, int $total): array
    {
        $totalWeight = array_sum($weights);
        $amounts = [];
        $distributed = 0;
        $count = count($weights);
        $i = 0;

        foreach ($weights as $index => $weight) {
            $i++;
            $amounts[$index] = $i === $count
                ? $total - $distributed
                : ($totalWeight > 0 ? intdiv($total * $weight, $totalWeight) : intdiv($total, $count));

            $distributed += $amounts[$index];
        }

        return $amounts;
    }
}
