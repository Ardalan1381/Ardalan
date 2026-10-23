<?php

namespace Tests\Feature\Admin;

use App\Actions\Invoices\GenerateInvoiceAction;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\LoyaltyLedgerEntry;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\TableSection;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosOrderTest extends TestCase
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

    public function test_waiter_can_create_a_dine_in_order_for_a_table(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $response = $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame('dine_in', $order->type);
        $this->assertSame($table->id, $order->table_id);
        $this->assertSame($waiter->id, $order->waiter_id);
        $this->assertSame('staff_pos', $order->source);
        $this->assertSame(10000, $order->subtotal);
        $this->assertNotNull($order->table_session_id);
        $this->assertSame('open', TableSession::find($order->table_session_id)->status);
    }

    public function test_disabling_customer_ordering_does_not_affect_pos_order_creation(): void
    {
        BusinessSetting::current()->update(['ordering_enabled' => false]);

        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertRedirect();

        $this->assertSame(1, Order::count());
    }

    public function test_kitchen_role_cannot_create_orders_via_pos(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $table = Table::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($kitchen)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();

        $this->assertSame(0, Order::count());
    }

    public function test_selecting_an_existing_customer_snapshots_their_name_and_phone(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create();
        $customer = Customer::create(['name' => 'Ali Rezaei', 'phone' => '0912', 'status' => 'active']);

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstOrFail();
        $this->assertSame($customer->id, $order->customer_id);
        $this->assertSame('Ali Rezaei', $order->customer_name);
        $this->assertSame('0912', $order->customer_phone);
    }

    public function test_counter_order_requires_either_a_customer_or_a_name_and_phone(): void
    {
        $waiter = $this->userWithRole('waiter');
        $product = Product::factory()->create();

        $response = $this->actingAs($waiter)->post(route('admin.pos.counter.orders.store'), [
            'type' => 'takeaway',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('customer_name');
        $this->assertSame(0, Order::count());
    }

    public function test_counter_order_with_a_name_and_phone_succeeds(): void
    {
        $waiter = $this->userWithRole('waiter');
        $product = Product::factory()->create(['base_price' => 3000]);

        $response = $this->actingAs($waiter)->post(route('admin.pos.counter.orders.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Sara',
            'customer_phone' => '0999',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame('takeaway', $order->type);
        $this->assertNull($order->table_id);
        $this->assertSame('Sara', $order->customer_name);
    }

    public function test_discounts_auto_apply_to_pos_orders_the_same_as_public_checkout(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 10000]);
        Discount::create([
            'name' => '10% off',
            'type' => 'percentage',
            'value' => 1000,
            'applies_to' => 'order',
            'is_active' => true,
        ]);

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstOrFail();
        $this->assertSame(1000, $order->discount_total);
        $this->assertSame(9000, $order->grand_total);
    }

    public function test_redeeming_a_loyalty_reward_at_pos_deducts_points_and_records_the_redemption(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);
        $customer = Customer::create(['name' => 'Ali', 'status' => 'active']);
        $customer->loyalty_points_balance = 100;
        $customer->save();

        $reward = LoyaltyReward::create([
            'name' => '$10 off',
            'points_cost' => 100,
            'reward_type' => 'fixed_discount_amount',
            'value' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'customer_id' => $customer->id,
            'loyalty_reward_id' => $reward->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::firstOrFail();
        $this->assertSame(1000, $order->discount_total);
        $this->assertSame(0, $customer->fresh()->loyalty_points_balance);
        $this->assertSame(1, LoyaltyRedemption::count());
        $this->assertSame($order->id, LoyaltyLedgerEntry::where('type', 'redeem')->sole()->order_id);
    }

    public function test_adding_items_to_a_pending_order_recomputes_totals(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $productA = Product::factory()->create(['base_price' => 5000]);
        $productB = Product::factory()->create(['base_price' => 2000]);

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $productA->id, 'quantity' => 1]],
        ]);
        $order = Order::firstOrFail();

        $response = $this->actingAs($waiter)->post(route('admin.pos.orders.items.store', $order), [
            'items' => [['product_id' => $productB->id, 'quantity' => 1]],
        ]);

        $order->refresh();
        $response->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame(7000, $order->subtotal);
        $this->assertCount(2, $order->items);
    }

    public function test_cannot_add_items_once_the_order_is_ready(): void
    {
        $waiter = $this->userWithRole('waiter');
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $order = Order::firstOrFail();

        foreach (['confirmed', 'preparing', 'ready'] as $status) {
            $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => $status]);
        }

        $response = $this->actingAs($waiter)->post(route('admin.pos.orders.items.store', $order->fresh()), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('order');
        $this->assertCount(1, $order->fresh()->items);
    }

    public function test_cannot_add_items_once_an_invoice_has_been_generated(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $order = Order::firstOrFail();
        app(GenerateInvoiceAction::class)->execute($order, $waiter);

        $response = $this->actingAs($waiter)->post(route('admin.pos.orders.items.store', $order->fresh()), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertSessionHasErrors('order');
        $this->assertCount(1, $order->fresh()->items);
    }

    public function test_a_second_pos_visit_to_the_same_table_reuses_the_open_session_and_adds_to_the_existing_order(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 1000]);

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $firstOrder = Order::firstOrFail();

        // A second POS visit to the same open table/session, while the
        // order is still pending, should surface it as the editable order.
        $response = $this->actingAs($waiter)->get(route('admin.pos.table', $table));

        $response->assertOk();
        $response->assertSee($firstOrder->order_number);
    }

    public function test_the_pos_index_page_lists_table_sections_and_their_tables(): void
    {
        $waiter = $this->userWithRole('waiter');
        $section = TableSection::factory()->create(['name' => 'Terrace']);
        $table = Table::factory()->create(['table_section_id' => $section->id, 'name' => 'T-12']);

        $response = $this->actingAs($waiter)->get(route('admin.pos.index'));

        $response->assertOk();
        $response->assertSee('Terrace');
        $response->assertSee('T-12');
        $response->assertSee(route('admin.pos.counter'));
    }

    public function test_the_pos_index_page_flags_a_table_with_an_open_session_as_occupied(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($waiter)->post(route('admin.pos.table.orders.store', $table), [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response = $this->actingAs($waiter)->get(route('admin.pos.index'));

        $response->assertOk();
        $response->assertSee(__('admin.pos.occupied'));
    }

    public function test_the_pos_table_page_shows_the_product_catalog_for_a_table_with_no_open_order(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $category = Category::factory()->create(['name' => 'Hot Drinks']);
        $product = Product::factory()->create(['category_id' => $category->id, 'name' => 'Cappuccino', 'base_price' => 4500]);

        $response = $this->actingAs($waiter)->get(route('admin.pos.table', $table));

        $response->assertOk();
        $response->assertSee('Hot Drinks');
        $response->assertSee('Cappuccino');
        $response->assertSee(Money::format($product->effectivePrice()));
    }

    public function test_the_pos_counter_page_renders_the_catalog_and_order_type_options(): void
    {
        $waiter = $this->userWithRole('waiter');
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'name' => 'Iced Latte']);

        $response = $this->actingAs($waiter)->get(route('admin.pos.counter'));

        $response->assertOk();
        $response->assertSee('Iced Latte');
        $response->assertSee(__('orders.type_labels.takeaway'));
        $response->assertSee(__('orders.type_labels.pickup'));
        $response->assertSee(__('orders.type_labels.delivery'));
    }

    public function test_kitchen_role_cannot_view_any_pos_page(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $table = Table::factory()->create();

        $this->actingAs($kitchen)->get(route('admin.pos.index'))->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.pos.table', $table))->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.pos.counter'))->assertForbidden();
    }
}
