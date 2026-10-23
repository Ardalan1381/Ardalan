<?php

namespace Tests\Feature\Admin;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockCount;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\Inventory\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasingAndStockCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->first()->id]);
    }

    private function gramUnit(): InventoryUnit
    {
        return InventoryUnit::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_factor' => 1]);
    }

    private function kilogramUnit(): InventoryUnit
    {
        return InventoryUnit::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'mass', 'base_factor' => 1000]);
    }

    // ---- Suppliers ----

    public function test_supplier_crud_happy_path(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->post(route('admin.suppliers.store'), ['name' => 'Acme Roasters'])
            ->assertRedirect(route('admin.suppliers.index'));

        $supplier = Supplier::where('name', 'Acme Roasters')->firstOrFail();

        $this->actingAs($manager)->put(route('admin.suppliers.update', $supplier), ['name' => 'Acme Roasters Co.'])
            ->assertRedirect(route('admin.suppliers.index'));
        $this->assertSame('Acme Roasters Co.', $supplier->fresh()->name);

        $this->actingAs($manager)->delete(route('admin.suppliers.destroy', $supplier));
        $this->assertModelMissing($supplier);
    }

    public function test_waiter_is_forbidden_from_suppliers(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.suppliers.index'))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.suppliers.store'), ['name' => 'x'])->assertForbidden();
    }

    // ---- Purchases: create -> receive -> pay ----

    public function test_full_purchase_create_and_receive_flow_increases_stock_and_average_cost(): void
    {
        $manager = $this->userWithRole('manager');
        $supplier = Supplier::create(['name' => 'Acme Roasters', 'is_active' => true]);
        $kg = $this->kilogramUnit();
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $this->gramUnit()->id, 'is_active' => true]);

        $response = $this->actingAs($manager)->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['inventory_item_id' => $item->id, 'unit_id' => $kg->id, 'quantity' => '5', 'unit_cost' => 10000],
            ],
        ]);

        $purchase = Purchase::firstOrFail();
        $response->assertRedirect(route('admin.purchases.show', $purchase));
        $this->assertSame(50000, $purchase->subtotal);

        $purchaseItem = $purchase->items()->firstOrFail();
        $this->actingAs($manager)->post(route('admin.purchases.receive', $purchase), [
            'receipts' => [
                ['purchase_item_id' => $purchaseItem->id, 'quantity' => '5'],
            ],
        ])->assertRedirect(route('admin.purchases.show', $purchase));

        $this->assertSame('received', $purchase->fresh()->status);
        $this->assertSame('5000.0000', $item->fresh()->current_stock);
        // 5kg at 10,000/kg = 50,000 total, received as 5000g -> 10/g.
        $this->assertSame(10, $item->fresh()->average_cost);

        $this->actingAs($manager)->patch(route('admin.purchases.payment-status', $purchase), ['payment_status' => 'paid'])
            ->assertRedirect(route('admin.purchases.show', $purchase));
        $this->assertSame('paid', $purchase->fresh()->payment_status);
    }

    public function test_waiter_cannot_view_or_create_purchases(): void
    {
        $waiter = $this->userWithRole('waiter');
        $supplier = Supplier::create(['name' => 'Acme', 'is_active' => true]);

        $this->actingAs($waiter)->get(route('admin.purchases.index'))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.purchases.store'), [
            'supplier_id' => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'items' => [],
        ])->assertForbidden();
    }

    // ---- Stock adjustments ----

    public function test_stock_adjustment_via_http_moves_stock(): void
    {
        $manager = $this->userWithRole('manager');
        $item = InventoryItem::create(['name' => 'Milk', 'base_unit_id' => $this->gramUnit()->id, 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '1000');

        $response = $this->actingAs($manager)->post(route('admin.stock-adjustments.store'), [
            'reason' => 'correction',
            'notes' => 'Recount',
            'lines' => [
                ['inventory_item_id' => $item->id, 'sign' => '-', 'quantity' => '50'],
            ],
        ]);

        $response->assertRedirect(route('admin.stock-adjustments.index'));
        $this->assertSame('950.0000', $item->fresh()->current_stock);
        $this->assertSame(1, StockAdjustment::count());
    }

    public function test_kitchen_cannot_record_stock_adjustments(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.stock-adjustments.create'))->assertForbidden();
    }

    // ---- Waste ----

    public function test_waste_record_via_http_decreases_stock(): void
    {
        $manager = $this->userWithRole('manager');
        $item = InventoryItem::create(['name' => 'Bread', 'base_unit_id' => $this->gramUnit()->id, 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '500');

        $response = $this->actingAs($manager)->post(route('admin.waste-records.store'), [
            'inventory_item_id' => $item->id,
            'quantity' => '100',
            'reason' => 'spoilage',
        ]);

        $response->assertRedirect(route('admin.waste-records.index'));
        $this->assertSame('400.0000', $item->fresh()->current_stock);
        $this->assertSame(1, WasteRecord::count());
    }

    // ---- Purchase returns ----

    public function test_purchase_return_via_http_decreases_stock_and_uses_inventory_adjust_permission(): void
    {
        $manager = $this->userWithRole('manager');
        $supplier = Supplier::create(['name' => 'Acme', 'is_active' => true]);
        $item = InventoryItem::create(['name' => 'Sugar', 'base_unit_id' => $this->gramUnit()->id, 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '500');

        $response = $this->actingAs($manager)->post(route('admin.purchase-returns.store'), [
            'supplier_id' => $supplier->id,
            'reason' => 'Damaged',
            'lines' => [
                ['inventory_item_id' => $item->id, 'quantity' => '50', 'unit_cost' => 100],
            ],
        ]);

        $response->assertRedirect(route('admin.purchase-returns.index'));
        $this->assertSame('450.0000', $item->fresh()->current_stock);

        // Cashier lacks inventory.adjust even though they might touch payments — confirms the
        // policy's documented choice (inventory.adjust, not purchases.manage).
        $cashier = $this->userWithRole('cashier');
        $this->actingAs($cashier)->get(route('admin.purchase-returns.create'))->assertForbidden();
    }

    // ---- Stock counts: start -> submit -> complete ----

    public function test_full_stock_count_lifecycle_via_http(): void
    {
        $manager = $this->userWithRole('manager');
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $this->gramUnit()->id, 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '1000');

        $startResponse = $this->actingAs($manager)->post(route('admin.stock-counts.store'), [
            'inventory_item_ids' => [$item->id],
        ]);

        $count = StockCount::firstOrFail();
        $startResponse->assertRedirect(route('admin.stock-counts.show', $count));
        $this->assertSame('1000.0000', $count->items()->first()->expected_quantity);

        $countItem = $count->items()->first();
        $this->actingAs($manager)->patch(route('admin.stock-counts.submit', $count), [
            'counted' => [
                ['stock_count_item_id' => $countItem->id, 'counted_quantity' => '950'],
            ],
        ])->assertRedirect(route('admin.stock-counts.show', $count));

        $this->assertSame('-50.0000', $count->fresh()->items()->first()->variance);

        $this->actingAs($manager)->patch(route('admin.stock-counts.complete', $count->fresh()))
            ->assertRedirect(route('admin.stock-counts.show', $count));

        $this->assertSame('completed', $count->fresh()->status);
        $this->assertSame('950.0000', $item->fresh()->current_stock);
    }

    public function test_select_all_active_checkbox_includes_every_active_item(): void
    {
        $manager = $this->userWithRole('manager');
        $unit = $this->gramUnit();
        $active = InventoryItem::create(['name' => 'Active Item', 'base_unit_id' => $unit->id, 'is_active' => true]);
        InventoryItem::create(['name' => 'Inactive Item', 'base_unit_id' => $unit->id, 'is_active' => false]);

        $this->actingAs($manager)->post(route('admin.stock-counts.store'), [
            'select_all_active' => '1',
        ]);

        $count = StockCount::firstOrFail();
        $this->assertCount(1, $count->items);
        $this->assertSame($active->id, $count->items->first()->inventory_item_id);
    }

    public function test_completing_a_stock_count_twice_shows_a_validation_error(): void
    {
        $manager = $this->userWithRole('manager');
        $item = InventoryItem::create(['name' => 'Tea', 'base_unit_id' => $this->gramUnit()->id, 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'opening_balance', '100');

        $this->actingAs($manager)->post(route('admin.stock-counts.store'), ['inventory_item_ids' => [$item->id]]);
        $count = StockCount::firstOrFail();

        $this->actingAs($manager)->patch(route('admin.stock-counts.complete', $count));
        $response = $this->actingAs($manager)->patch(route('admin.stock-counts.complete', $count->fresh()));

        $response->assertSessionHasErrors('count');
    }
}
