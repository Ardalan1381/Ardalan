<?php

namespace Tests\Feature;

use App\Actions\AddItemsToOrderAction;
use App\Actions\Billing\CreateBillSplitAction;
use App\Actions\CreateStaffOrderAction;
use App\Actions\TransitionOrderStatusAction;
use App\Models\Discount;
use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Table;
use App\Models\User;
use App\Services\Inventory\StockMovementService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * AUD-04 (adding items to an in-flight order) and AUD-05 (reversing
 * exactly what was consumed, not what today's recipe says).
 */
class OrderInventoryIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryUnit $gram;

    private InventoryItem $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
        $this->gram = InventoryUnit::create(['name' => 'Gram', 'abbreviation' => 'g', 'type' => 'mass', 'base_factor' => 1]);
        $this->coffee = InventoryItem::create(['name' => 'Coffee', 'base_unit_id' => $this->gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($this->coffee, 'opening_balance', '1000');
    }

    private function productUsing(string $grams, int $price = 1000): Product
    {
        $product = Product::factory()->create(['base_price' => $price]);
        $product->recipeItems()->create(['inventory_item_id' => $this->coffee->id, 'quantity' => $grams, 'unit_id' => $this->gram->id]);

        return $product;
    }

    private function dineInOrder(array $items): Order
    {
        return app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => Table::factory()->create()->id,
            'items' => $items,
        ], $this->owner);
    }

    private function transition(Order $order, string $status): void
    {
        app(TransitionOrderStatusAction::class)->execute($order->fresh(), $status, $this->owner);
    }

    private function stock(): string
    {
        return $this->coffee->fresh()->current_stock;
    }

    public function test_cancelling_reverses_the_historical_consumption_even_after_the_recipe_changed(): void
    {
        $latte = $this->productUsing('18');
        $order = $this->dineInOrder([['product_id' => $latte->id, 'quantity' => 2]]);
        $this->transition($order, 'confirmed');
        $this->assertSame('964.0000', $this->stock());

        // The recipe is edited after the order was made.
        $latte->recipeItems()->update(['quantity' => '30']);

        $this->transition($order, 'cancelled');

        // Exactly the 36g that were taken come back — not 60g.
        $this->assertSame('1000.0000', $this->stock());
    }

    public function test_cancelling_does_not_reverse_a_recipe_line_added_after_confirmation(): void
    {
        $latte = $this->productUsing('18');
        $milk = InventoryItem::create(['name' => 'Milk', 'base_unit_id' => $this->gram->id, 'is_active' => true]);
        app(StockMovementService::class)->record($milk, 'opening_balance', '500');

        $order = $this->dineInOrder([['product_id' => $latte->id, 'quantity' => 1]]);
        $this->transition($order, 'confirmed');
        $latte->recipeItems()->create(['inventory_item_id' => $milk->id, 'quantity' => '200', 'unit_id' => $this->gram->id]);

        $this->transition($order, 'cancelled');

        $this->assertSame('500.0000', $milk->fresh()->current_stock);
        $this->assertSame('1000.0000', $this->stock());
    }

    public function test_items_added_to_a_confirmed_order_are_consumed_once_and_existing_items_keep_their_identity(): void
    {
        $latte = $this->productUsing('18');
        $espresso = $this->productUsing('9');
        $order = $this->dineInOrder([['product_id' => $latte->id, 'quantity' => 1]]);
        $this->transition($order, 'confirmed');
        $originalItemId = $order->items()->sole()->id;

        app(AddItemsToOrderAction::class)->execute($order->fresh(), [['product_id' => $espresso->id, 'quantity' => 2]], $this->owner);

        $this->assertTrue($order->items()->whereKey($originalItemId)->exists(), 'existing order item was replaced');
        $this->assertSame(2, $order->items()->count());
        // 18 (latte, once) + 2 * 9 (espresso) = 36g.
        $this->assertSame('964.0000', $this->stock());
        $this->assertSame(2, StockMovement::where('type', 'sale_consumption')->count());

        $this->transition($order, 'cancelled');
        $this->assertSame('1000.0000', $this->stock());
    }

    public function test_items_added_to_a_pending_order_are_consumed_only_on_confirmation(): void
    {
        $latte = $this->productUsing('18');
        $order = $this->dineInOrder([['product_id' => $latte->id, 'quantity' => 1]]);

        app(AddItemsToOrderAction::class)->execute($order->fresh(), [['product_id' => $latte->id, 'quantity' => 1]], $this->owner);
        $this->assertSame('1000.0000', $this->stock());

        $this->transition($order, 'confirmed');
        $this->assertSame('964.0000', $this->stock());
    }

    public function test_cannot_add_items_once_the_bill_has_been_split(): void
    {
        $latte = $this->productUsing('18');
        $order = $this->dineInOrder([['product_id' => $latte->id, 'quantity' => 1]]);
        app(CreateBillSplitAction::class)->execute($order, 'even', [['label' => 'A'], ['label' => 'B']], $this->owner);

        $this->expectException(ValidationException::class);
        app(AddItemsToOrderAction::class)->execute($order->fresh(), [['product_id' => $latte->id, 'quantity' => 1]], $this->owner);
    }

    public function test_a_free_item_from_a_discount_is_not_turned_into_a_paid_item_when_adding_items(): void
    {
        $latte = $this->productUsing('18', price: 1000);
        $cookie = Product::factory()->create(['base_price' => 300]);
        Discount::create([
            'name' => 'Free cookie', 'type' => 'free_item', 'free_item_product_id' => $cookie->id, 'get_quantity' => 1,
            'applies_to' => 'order', 'priority' => 1, 'is_stackable' => false, 'is_active' => true,
        ]);

        $order = $this->dineInOrder([['product_id' => $latte->id, 'quantity' => 1]]);
        $this->assertSame(1000, $order->grand_total);
        $this->assertSame(2, $order->items()->count());

        app(AddItemsToOrderAction::class)->execute($order->fresh(), [['product_id' => $latte->id, 'quantity' => 1]], $this->owner);

        $order->refresh();
        $this->assertSame(2000, $order->grand_total);
        $this->assertSame(1, $order->items()->where('product_id', $cookie->id)->count());
        $this->assertSame(1, (int) $order->items()->where('product_id', $cookie->id)->sum('quantity'));
    }
}
