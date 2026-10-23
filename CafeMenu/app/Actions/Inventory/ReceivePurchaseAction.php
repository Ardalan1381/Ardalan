<?php

namespace App\Actions\Inventory;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receiving a purchase (in full or in part) is the only way a purchase's
 * line items turn into real stock — every unit received creates a
 * purchase_receipt stock movement, converted into the item's own base unit
 * since a purchase may be placed in a different unit than the item is
 * tracked in.
 */
class ReceivePurchaseAction
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array<int, array{purchase_item_id: int, quantity: string}>  $receipts
     */
    public function execute(Purchase $purchase, array $receipts, User $actor): Purchase
    {
        if (in_array($purchase->status, ['received', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'purchase' => "Cannot receive a purchase that is already \"{$purchase->status}\".",
            ]);
        }

        return DB::transaction(function () use ($purchase, $receipts, $actor) {
            foreach ($receipts as $receipt) {
                $quantity = (string) $receipt['quantity'];

                if (Quantity::compare($quantity, '0') <= 0) {
                    continue;
                }

                /** @var PurchaseItem $item */
                $item = $purchase->items()->with(['inventoryItem', 'unit'])->findOrFail($receipt['purchase_item_id']);

                $remaining = Quantity::subtract((string) $item->quantity, (string) $item->quantity_received);

                if (Quantity::compare($quantity, $remaining) > 0) {
                    throw ValidationException::withMessages([
                        'quantity' => "Cannot receive more of \"{$item->inventoryItem->name}\" than was ordered.",
                    ]);
                }

                $baseQuantity = Quantity::convert($quantity, $item->unit, $item->inventoryItem->baseUnit);

                // unit_cost is priced per the purchase's own unit (e.g. per
                // kg), but average_cost must be per the item's base unit
                // (e.g. per gram) — derive it from the actual cost of what's
                // being received rather than passing unit_cost through
                // unconverted, which would overstate it by the same factor
                // as the unit conversion (1000x for kg -> g).
                $receivedCost = (int) round((float) bcmul($quantity, (string) $item->unit_cost, 6));
                $baseUnitCost = Quantity::compare($baseQuantity, '0') > 0
                    ? (int) round((float) bcdiv((string) $receivedCost, $baseQuantity, 6))
                    : $item->unit_cost;

                $this->stockMovements->record(
                    $item->inventoryItem,
                    'purchase_receipt',
                    $baseQuantity,
                    unitCost: $baseUnitCost,
                    reference: $purchase,
                    actor: $actor,
                );

                $item->update(['quantity_received' => Quantity::add((string) $item->quantity_received, $quantity)]);
            }

            $purchase->refresh();
            $fullyReceived = $purchase->items->every(
                fn (PurchaseItem $item) => Quantity::compare((string) $item->quantity_received, (string) $item->quantity) >= 0
            );
            $anyReceived = $purchase->items->contains(
                fn (PurchaseItem $item) => Quantity::compare((string) $item->quantity_received, '0') > 0
            );

            $purchase->update([
                'status' => $fullyReceived ? 'received' : ($anyReceived ? 'partially_received' : $purchase->status),
            ]);

            return $purchase->fresh('items');
        });
    }
}
