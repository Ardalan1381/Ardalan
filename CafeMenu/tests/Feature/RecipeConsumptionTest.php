<?php

namespace Tests\Feature;

use App\Actions\TransitionOrderStatusAction;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RecipeConsumptionTest extends TestCase
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

    private function placeOrder(Product $product, int $quantity = 1): Order
    {
        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ])->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    private function transition(Order $order, string $status, User $actor): Order
    {
        return app(TransitionOrderStatusAction::class)->execute($order->fresh(), $status, $actor);
    }

    public function test_confirming_an_order_consumes_the_products_recipe(): void
    {
        $owner = $this->owner();
        $gram = $this->gramUnit();
        $coffee = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($coffee, 'opening_balance', '1000');

        $product = Product::factory()->create();
        $product->recipeItems()->create(['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id]);

        $order = $this->placeOrder($product, quantity: 2);
        $this->transition($order, 'confirmed', $owner);

        // 18g * 2 = 36g consumed.
        $this->assertSame('964.0000', $coffee->fresh()->current_stock);
        $this->assertNotNull($order->fresh()->inventory_consumed_at);
        $this->assertSame(1, StockMovement::where('type', 'sale_consumption')->count());
    }

    public function test_add_on_recipe_is_also_consumed_and_scaled_by_line_quantity(): void
    {
        $owner = $this->owner();
        $gram = $this->gramUnit();
        $syrup = InventoryItem::create(['name' => 'Caramel Syrup', 'base_unit_id' => $gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($syrup, 'opening_balance', '1000');

        $product = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Add-ons',
            'selection_type' => 'multiple',
            'min_select' => 0,
            'is_active' => true,
        ]);
        $option = $group->options()->create(['name' => 'Caramel Syrup', 'price_delta' => 500, 'is_active' => true]);
        $option->recipeItems()->create(['inventory_item_id' => $syrup->id, 'quantity' => '10', 'unit_id' => $gram->id]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'option_ids' => [$option->id]]],
        ])->assertOk();
        $order = Order::latest('id')->firstOrFail();

        $this->transition($order, 'confirmed', $owner);

        // 10g * 3 (line quantity) = 30g.
        $this->assertSame('970.0000', $syrup->fresh()->current_stock);
    }

    public function test_confirming_fails_and_rolls_back_when_stock_is_insufficient(): void
    {
        $owner = $this->owner();
        $gram = $this->gramUnit();
        $milk = InventoryItem::create(['name' => 'Milk', 'base_unit_id' => $gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($milk, 'opening_balance', '10');

        $product = Product::factory()->create();
        $product->recipeItems()->create(['inventory_item_id' => $milk->id, 'quantity' => '200', 'unit_id' => $gram->id]);

        $order = $this->placeOrder($product);

        try {
            $this->transition($order, 'confirmed', $owner);
            $this->fail('Expected a ValidationException for insufficient stock.');
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNull($order->fresh()->inventory_consumed_at);
        $this->assertSame('10.0000', $milk->fresh()->current_stock);
    }

    public function test_cancelling_a_confirmed_order_reverses_the_consumed_stock(): void
    {
        $owner = $this->owner();
        $gram = $this->gramUnit();
        $coffee = InventoryItem::create(['name' => 'Coffee Beans', 'base_unit_id' => $gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($coffee, 'opening_balance', '1000');

        $product = Product::factory()->create();
        $product->recipeItems()->create(['inventory_item_id' => $coffee->id, 'quantity' => '18', 'unit_id' => $gram->id]);

        $order = $this->placeOrder($product);
        $this->transition($order, 'confirmed', $owner);
        $this->assertSame('982.0000', $coffee->fresh()->current_stock);

        $this->transition($order, 'cancelled', $owner);

        $this->assertSame('1000.0000', $coffee->fresh()->current_stock);
        $this->assertNull($order->fresh()->inventory_consumed_at);
        $this->assertSame(1, StockMovement::where('type', 'sale_reversal')->count());
    }

    public function test_products_with_no_recipe_do_not_error_on_confirmation(): void
    {
        $owner = $this->owner();
        $product = Product::factory()->create();
        $order = $this->placeOrder($product);

        $this->transition($order, 'confirmed', $owner);

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->inventory_consumed_at);
    }
}
