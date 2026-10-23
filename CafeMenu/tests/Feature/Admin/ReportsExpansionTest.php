<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Actions\Shifts\CloseShiftAction;
use App\Actions\Shifts\OpenShiftAction;
use App\Actions\TransitionOrderStatusAction;
use App\Models\Category;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Table;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsExpansionTest extends TestCase
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

    public function test_reports_shows_category_sales(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create(['name' => 'Coffee']);
        $product = Product::factory()->create(['category_id' => $category->id, 'base_price' => 5000]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway', 'customer_name' => 'Jane', 'customer_phone' => '555',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $categorySales = $response->viewData('categorySales');

        $this->assertSame('Coffee', $categorySales->first()->category_name);
        $this->assertEquals(5000, $categorySales->first()->total_revenue);
    }

    public function test_reports_shows_top_customers(): void
    {
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('waiter');
        $customer = Customer::create(['name' => 'Ali Rezaei', 'status' => 'active']);
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 7000]);

        app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in', 'table_id' => $table->id, 'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $topCustomers = $response->viewData('topCustomers');

        $this->assertSame('Ali Rezaei', $topCustomers->first()->customer_name);
        $this->assertEquals(7000, $topCustomers->first()->total_spent);
    }

    public function test_reports_shows_staff_order_performance(): void
    {
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('waiter');
        $waiter->update(['name' => 'Waiter Wendy']);
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 3000]);

        app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in', 'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $performance = $response->viewData('staffOrderPerformance');

        $this->assertSame('Waiter Wendy', $performance->first()->user_name);
        $this->assertEquals(1, $performance->first()->order_count);
    }

    public function test_reports_shows_closed_shifts_with_cash_difference(): void
    {
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('waiter');
        $waiter->update(['name' => 'Waiter Wendy']);

        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);
        app(CloseShiftAction::class)->execute($shift, 49000);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $shifts = $response->viewData('closedShifts');

        $this->assertSame('Waiter Wendy', $shifts->first()->user->name);
        $this->assertSame(-1000, $shifts->first()->cash_difference);
    }

    public function test_reports_shows_inventory_analytics(): void
    {
        $owner = $this->userWithRole('owner');
        $item = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $this->gramUnit()->id, 'min_stock_level' => '500', 'is_active' => true]);
        app(StockMovementService::class)->record($item, 'purchase_receipt', '100', unitCost: 50);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));

        $this->assertSame(5000, $response->viewData('stockValuation')['total_value']); // 100 * 50
        $this->assertSame(1, $response->viewData('lowStockCount')); // 100 <= 500
    }

    public function test_reports_shows_purchase_spend_by_supplier(): void
    {
        $owner = $this->userWithRole('owner');
        $supplier = Supplier::create(['name' => 'Acme Roasters', 'is_active' => true]);
        Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'total_cost' => 30000,
        ]);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $spend = $response->viewData('purchaseSpendBySupplier');

        $this->assertSame('Acme Roasters', $spend->first()->supplier_name);
        $this->assertEquals(30000, $spend->first()->total_spend);
    }

    public function test_owner_sees_profit_analytics_but_manager_does_not(): void
    {
        $owner = $this->userWithRole('owner');
        $manager = $this->userWithRole('manager');
        $gram = $this->gramUnit();
        $coffee = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($coffee, 'purchase_receipt', '1000', unitCost: 10);

        $product = Product::factory()->create(['base_price' => 5000, 'name' => 'Latte']);
        $product->recipeItems()->create(['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id]);

        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in', 'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);
        // Profit analytics counts realised (completed) sales only — see
        // ProfitAnalyticsService and docs/engineering/DECISIONS.md D-03.
        $this->complete($order, $waiter);

        $ownerResponse = $this->actingAs($owner)->get(route('admin.reports.index'));
        $this->assertTrue($ownerResponse->viewData('canViewProfit'));
        $profitRow = $ownerResponse->viewData('profitByProduct')->first();
        $this->assertSame('Latte', $profitRow->product_name);
        $this->assertSame(5000, $profitRow->revenue);
        $this->assertSame(180, $profitRow->cogs); // 18g * 10 minor-units/g
        $this->assertSame(4820, $profitRow->profit);
        $this->assertTrue($profitRow->has_recipe);

        $managerResponse = $this->actingAs($manager)->get(route('admin.reports.index'));
        $this->assertFalse($managerResponse->viewData('canViewProfit'));
        $this->assertNull($managerResponse->viewData('profitByProduct'));
    }

    public function test_product_with_no_recipe_is_flagged_rather_than_reported_as_full_margin(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create(['base_price' => 5000, 'name' => 'Bottled Water']);
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in', 'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);
        $this->complete($order, $waiter);

        $response = $this->actingAs($owner)->get(route('admin.reports.index'));
        $row = $response->viewData('profitByProduct')->first();

        $this->assertFalse($row->has_recipe);
        $this->assertSame(0, $row->cogs);
        $this->assertSame(5000, $row->profit);
    }

    private function complete(Order $order, User $actor): void
    {
        foreach (['confirmed', 'preparing', 'ready', 'completed'] as $status) {
            app(TransitionOrderStatusAction::class)->execute($order->fresh(), $status, $actor);
        }
    }
}
