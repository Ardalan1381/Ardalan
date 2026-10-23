<?php

namespace Tests\Feature;

use App\Actions\Inventory\CompleteStockCountAction;
use App\Actions\Inventory\CreatePurchaseAction;
use App\Actions\Inventory\ReceivePurchaseAction;
use App\Actions\Inventory\RecordPurchaseReturnAction;
use App\Actions\Inventory\RecordStockAdjustmentAction;
use App\Actions\Inventory\RecordWasteAction;
use App\Actions\Inventory\StartStockCountAction;
use App\Actions\Inventory\SubmitStockCountAction;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use App\Support\Quantity;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function owner(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    }

    private function gramUnit(): InventoryUnit
    {
        return InventoryUnit::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_factor' => 1]);
    }

    private function kilogramUnit(): InventoryUnit
    {
        return InventoryUnit::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'mass', 'base_factor' => 1000]);
    }

    private function coffeeItem(): InventoryItem
    {
        return InventoryItem::create([
            'name' => 'Coffee Beans',
            'base_unit_id' => $this->gramUnit()->id,
            'is_active' => true,
        ]);
    }

    public function test_quantity_convert_handles_same_type_units(): void
    {
        $gram = $this->gramUnit();
        $kg = $this->kilogramUnit();

        $this->assertSame('2000.0000', Quantity::convert('2', $kg, $gram));
        $this->assertSame('2.0000', Quantity::convert('2000', $gram, $kg));
    }

    public function test_quantity_convert_rejects_incompatible_unit_types(): void
    {
        $gram = $this->gramUnit();
        $milliliter = InventoryUnit::create(['name' => 'Milliliter', 'abbreviation' => 'ml', 'type' => 'volume', 'base_factor' => 1]);

        $this->expectException(\InvalidArgumentException::class);
        Quantity::convert('100', $gram, $milliliter);
    }

    public function test_stock_movement_service_increases_and_decreases_stock(): void
    {
        $item = $this->coffeeItem();
        $service = app(StockMovementService::class);

        $service->record($item, 'opening_balance', '5000');
        $this->assertSame('5000.0000', $item->fresh()->current_stock);

        $service->record($item, 'sale_consumption', '-18');
        $this->assertSame('4982.0000', $item->fresh()->current_stock);
    }

    public function test_stock_movement_service_blocks_negative_stock_by_default(): void
    {
        $item = $this->coffeeItem();
        $service = app(StockMovementService::class);
        $service->record($item, 'opening_balance', '10');

        $this->expectException(ValidationException::class);
        $service->record($item, 'sale_consumption', '-20');
    }

    public function test_stock_movement_service_allows_negative_when_explicitly_permitted(): void
    {
        $item = $this->coffeeItem();
        $service = app(StockMovementService::class);
        $service->record($item, 'opening_balance', '10');

        $service->record($item, 'adjustment_decrease', '-20', allowNegative: true);
        $this->assertSame('-10.0000', $item->fresh()->current_stock);
    }

    public function test_stock_movement_service_computes_weighted_average_cost_on_purchase_receipt(): void
    {
        $item = $this->coffeeItem();
        $service = app(StockMovementService::class);

        // 100 units @ 200 = 20,000; then 100 units @ 400 = 40,000.
        // Total 200 units, total value 60,000 -> average 300.
        $service->record($item, 'purchase_receipt', '100', unitCost: 200);
        $this->assertSame(200, $item->fresh()->average_cost);

        $service->record($item, 'purchase_receipt', '100', unitCost: 400);
        $this->assertSame(300, $item->fresh()->average_cost);
    }

    public function test_receiving_a_purchase_converts_units_and_creates_a_movement(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem(); // tracked in grams
        $kg = $this->kilogramUnit();
        $supplier = Supplier::create(['name' => 'Acme Roasters', 'is_active' => true]);

        $purchase = app(CreatePurchaseAction::class)->execute([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['inventory_item_id' => $item->id, 'unit_id' => $kg->id, 'quantity' => '5', 'unit_cost' => 10000],
            ],
        ], $owner);

        $this->assertSame(50000, $purchase->subtotal);

        $purchaseItem = $purchase->items->first();
        $updated = app(ReceivePurchaseAction::class)->execute($purchase, [
            ['purchase_item_id' => $purchaseItem->id, 'quantity' => '5'],
        ], $owner);

        $this->assertSame('received', $updated->status);
        // 5kg received into a gram-tracked item -> 5000g.
        $this->assertSame('5000.0000', $item->fresh()->current_stock);
        $this->assertSame(Purchase::class, StockMovement::query()->latest()->first()->reference_type);
    }

    public function test_partial_receipt_marks_the_purchase_partially_received(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        $gram = InventoryUnit::where('type', 'mass')->where('abbreviation', 'g')->first();
        $supplier = Supplier::create(['name' => 'Acme Roasters', 'is_active' => true]);

        $purchase = app(CreatePurchaseAction::class)->execute([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['inventory_item_id' => $item->id, 'unit_id' => $gram->id, 'quantity' => '1000', 'unit_cost' => 10],
            ],
        ], $owner);

        $purchaseItem = $purchase->items->first();
        $updated = app(ReceivePurchaseAction::class)->execute($purchase, [
            ['purchase_item_id' => $purchaseItem->id, 'quantity' => '400'],
        ], $owner);

        $this->assertSame('partially_received', $updated->status);
    }

    public function test_cannot_receive_more_than_was_ordered(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        $gram = InventoryUnit::where('type', 'mass')->where('abbreviation', 'g')->first();
        $supplier = Supplier::create(['name' => 'Acme Roasters', 'is_active' => true]);

        $purchase = app(CreatePurchaseAction::class)->execute([
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['inventory_item_id' => $item->id, 'unit_id' => $gram->id, 'quantity' => '100', 'unit_cost' => 10],
            ],
        ], $owner);

        $purchaseItem = $purchase->items->first();

        $this->expectException(ValidationException::class);
        app(ReceivePurchaseAction::class)->execute($purchase, [
            ['purchase_item_id' => $purchaseItem->id, 'quantity' => '150'],
        ], $owner);
    }

    public function test_stock_adjustment_records_a_movement_and_reaches_the_correct_sign(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        app(StockMovementService::class)->record($item, 'opening_balance', '1000');

        $adjustment = app(RecordStockAdjustmentAction::class)->execute(
            ['reason' => 'correction', 'notes' => 'Recount'],
            [['inventory_item_id' => $item->id, 'quantity_delta' => '-50']],
            $owner,
        );

        $this->assertSame('950.0000', $item->fresh()->current_stock);
        $this->assertSame(1, StockMovement::where('type', 'adjustment_decrease')->count());
        $this->assertSame($adjustment->id, $adjustment->fresh()->id);
    }

    public function test_recording_waste_decreases_stock_and_defaults_to_average_cost(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        app(StockMovementService::class)->record($item, 'purchase_receipt', '1000', unitCost: 50);
        $item->refresh();

        $waste = app(RecordWasteAction::class)->execute($item, [
            'quantity' => '100',
            'reason' => 'spoilage',
        ], $owner);

        $this->assertSame(50, $waste->unit_cost);
        $this->assertSame('900.0000', $item->fresh()->current_stock);
    }

    public function test_purchase_return_decreases_stock_and_computes_total_amount(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        $supplier = Supplier::create(['name' => 'Acme Roasters', 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '500');

        $return = app(RecordPurchaseReturnAction::class)->execute(
            ['supplier_id' => $supplier->id, 'reason' => 'Damaged in transit'],
            [['inventory_item_id' => $item->id, 'quantity' => '50', 'unit_cost' => 100]],
            $owner,
        );

        $this->assertSame(5000, $return->total_amount);
        $this->assertSame('450.0000', $item->fresh()->current_stock);
    }

    public function test_stock_count_lifecycle_corrects_stock_to_the_counted_quantity(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        app(StockMovementService::class)->record($item, 'opening_balance', '1000');

        $count = app(StartStockCountAction::class)->execute([$item->id], $owner);
        $this->assertSame('1000.0000', $count->items->first()->expected_quantity);

        // Stock drifts (a sale happens) while the count is in progress.
        app(StockMovementService::class)->record($item, 'sale_consumption', '-50');

        app(SubmitStockCountAction::class)->execute($count, [
            ['stock_count_item_id' => $count->items->first()->id, 'counted_quantity' => '900'],
        ]);
        $this->assertSame('-100.0000', $count->fresh()->items->first()->variance);

        $completed = app(CompleteStockCountAction::class)->execute($count->fresh(), $owner);

        $this->assertSame('completed', $completed->status);
        // Live stock was 950 (1000 - 50) at completion time; corrected to
        // the physically counted 900, NOT to 1000 + variance(-100) = 900
        // coincidentally matching here — the important guarantee is it
        // lands exactly on the counted figure regardless of the drift.
        $this->assertSame('900.0000', $item->fresh()->current_stock);
        $this->assertSame(1, StockMovement::where('type', 'count_correction')->count());
    }

    public function test_completing_a_stock_count_twice_is_rejected(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        app(StockMovementService::class)->record($item, 'opening_balance', '100');

        $count = app(StartStockCountAction::class)->execute([$item->id], $owner);
        app(SubmitStockCountAction::class)->execute($count, [
            ['stock_count_item_id' => $count->items->first()->id, 'counted_quantity' => '100'],
        ]);
        app(CompleteStockCountAction::class)->execute($count->fresh(), $owner);

        $this->expectException(ValidationException::class);
        app(CompleteStockCountAction::class)->execute($count->fresh(), $owner);
    }

    public function test_low_stock_command_notifies_once_until_stock_recovers(): void
    {
        $owner = $this->owner();
        $item = $this->coffeeItem();
        $item->update(['min_stock_level' => '100']);
        app(StockMovementService::class)->record($item, 'opening_balance', '50');

        $this->artisan('inventory:check-low-stock');
        $this->assertNotNull($item->fresh()->low_stock_alerted_at);
        $this->assertSame(1, $owner->notifications()->count());

        // Running it again before stock recovers must not send a duplicate.
        $this->artisan('inventory:check-low-stock');
        $this->assertSame(1, $owner->notifications()->count());

        // Once stock recovers above the minimum, the alert flag clears...
        app(StockMovementService::class)->record($item, 'purchase_receipt', '200', unitCost: 10);
        $this->assertNull($item->fresh()->low_stock_alerted_at);
    }
}
