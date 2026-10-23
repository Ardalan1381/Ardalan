<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductOptionGroup;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::current()->update([
            'accepts_dine_in' => true,
            'accepts_takeaway' => true,
            'accepts_pickup' => true,
            'accepts_delivery' => false,
        ]);
    }

    private function checkoutJson(array $payload): TestResponse
    {
        return $this->postJson(route('checkout.store'), $payload);
    }

    public function test_valid_takeaway_checkout_creates_an_order_atomically(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);

        $response = $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertOk()->assertJsonStructure(['redirect']);

        $order = Order::firstOrFail();
        $this->assertSame(10000, $order->subtotal);
        $this->assertSame(10000, $order->grand_total);
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertNotEmpty($order->order_number);
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame($product->name, $order->items->first()->product_name_snapshot);
    }

    public function test_client_submitted_price_is_ignored_server_recalculates(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                // Attacker tries to inject a fake price field — it doesn't exist in validated data at all.
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price_snapshot' => 1],
            ],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(5000, $order->subtotal);
    }

    public function test_checkout_rejects_a_nonexistent_product_id(): void
    {
        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => 999999, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_an_unavailable_product(): void
    {
        $product = Product::factory()->create(['is_available' => false]);

        $response = $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_an_inactive_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_a_product_whose_category_is_inactive(): void
    {
        $product = Product::factory()->create();
        $product->category->update(['is_active' => false]);

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_an_option_that_does_not_belong_to_the_product(): void
    {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $productB->id,
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 0,
            'is_active' => true,
        ]);
        $foreignOption = $group->options()->create(['name' => 'Large', 'price_delta' => 500, 'is_active' => true]);

        $response = $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1, 'option_ids' => [$foreignOption->id]],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_computes_total_including_option_price_deltas(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 1,
            'is_active' => true,
        ]);
        $option = $group->options()->create(['name' => 'Large', 'price_delta' => 700, 'is_active' => true]);

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'option_ids' => [$option->id]],
            ],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(11400, $order->subtotal); // (5000 + 700) * 2
    }

    public function test_checkout_rejects_when_required_option_group_is_not_satisfied(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 1,
            'is_required' => true,
            'is_active' => true,
        ]);

        $response = $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_too_many_selections_for_single_selection_group(): void
    {
        $product = Product::factory()->create();
        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Size',
            'selection_type' => 'single',
            'min_select' => 1,
            'max_select' => 1,
            'is_active' => true,
        ]);
        $small = $group->options()->create(['name' => 'Small', 'price_delta' => 0, 'is_active' => true]);
        $large = $group->options()->create(['name' => 'Large', 'price_delta' => 500, 'is_active' => true]);

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'option_ids' => [$small->id, $large->id]],
            ],
        ])->assertUnprocessable();

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_disabled_order_type(): void
    {
        BusinessSetting::current()->update(['accepts_delivery' => false]);
        $product = Product::factory()->create();

        $this->checkoutJson([
            'type' => 'delivery',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'delivery_address' => '123 Main St',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_rejects_all_orders_when_ordering_is_disabled(): void
    {
        BusinessSetting::current()->update(['ordering_enabled' => false]);
        $product = Product::factory()->create();

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->assertSame(0, Order::count());
    }

    public function test_dine_in_checkout_requires_a_scanned_table(): void
    {
        $product = Product::factory()->create();

        $response = $this->checkoutJson([
            'type' => 'dine_in',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertSame(0, Order::count());
    }

    public function test_dine_in_checkout_succeeds_after_scanning_table_qr(): void
    {
        $table = Table::factory()->create();
        $product = Product::factory()->create();

        $this->get('/t/'.$table->qr_token);

        $response = $this->checkoutJson([
            'type' => 'dine_in',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();
        $order = Order::firstOrFail();
        $this->assertSame($table->id, $order->table_id);
    }

    public function test_dine_in_checkout_rejected_when_table_is_out_of_service(): void
    {
        $table = Table::factory()->create(['status' => 'out_of_service']);
        $product = Product::factory()->create();

        $this->get('/t/'.$table->qr_token);

        $this->checkoutJson([
            'type' => 'dine_in',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();

        $this->assertSame(0, Order::count());
    }

    public function test_checkout_requires_at_least_one_item(): void
    {
        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_takeaway_checkout_requires_customer_name_and_phone(): void
    {
        $product = Product::factory()->create();

        $response = $this->checkoutJson([
            'type' => 'takeaway',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['customer_name', 'customer_phone']);

        // The error text a customer actually sees must be the friendly copy
        // from lang/*/checkout.php — not Laravel's raw generated message,
        // which inserts unlocalized field/rule internals verbatim (e.g.
        // "The customer name field is required unless type is dine_in").
        $errors = $response->json('errors');
        $this->assertSame(__('checkout.errors.customer_name_required'), $errors['customer_name'][0]);
        $this->assertSame(__('checkout.errors.customer_phone_required'), $errors['customer_phone'][0]);
        $this->assertStringNotContainsString('dine_in', $errors['customer_name'][0]);
    }

    public function test_order_confirmation_page_is_publicly_viewable_by_uuid(): void
    {
        $product = Product::factory()->create();

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::firstOrFail();

        $this->get(route('orders.show', $order))->assertOk()->assertSee($order->order_number);
    }

    public function test_order_confirmation_page_returns_404_for_unknown_uuid(): void
    {
        $this->get('/orders/00000000-0000-0000-0000-000000000000')->assertNotFound();
    }

    public function test_tax_and_service_charge_are_applied_when_enabled(): void
    {
        BusinessSetting::current()->update([
            'tax_enabled' => true,
            'tax_rate_bps' => 1000, // 10%
            'service_charge_enabled' => true,
            'service_charge_rate_bps' => 500, // 5%
        ]);
        $product = Product::factory()->create(['base_price' => 10000]);

        $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(10000, $order->subtotal);
        $this->assertSame(1000, $order->tax_total);
        $this->assertSame(500, $order->service_charge_total);
        $this->assertSame(11500, $order->grand_total);
    }

    public function test_pickup_checkout_with_cash_payment_method_creates_a_pending_cash_payment(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);

        $this->checkoutJson([
            'type' => 'pickup',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->payment_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'cash',
            'status' => 'pending',
            'amount' => 10000,
        ]);
    }

    public function test_cash_payment_method_is_rejected_for_non_pickup_order_types(): void
    {
        $product = Product::factory()->create();

        $response = $this->checkoutJson([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_cash_payment_method_is_rejected_when_business_does_not_accept_it(): void
    {
        BusinessSetting::current()->update(['accepts_cash_on_pickup' => false]);
        $product = Product::factory()->create();

        $response = $this->checkoutJson([
            'type' => 'pickup',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_order_tracking_page_shows_cash_on_pickup_notice(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);

        $this->checkoutJson([
            'type' => 'pickup',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $order = Order::firstOrFail();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee(__('payments.cash_on_pickup.title'))
            ->assertDontSee(__('payments.pay_online.title'));
    }

    public function test_pickup_checkout_without_payment_method_creates_no_payment_record(): void
    {
        $product = Product::factory()->create();

        $this->checkoutJson([
            'type' => 'pickup',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(0, $order->payments()->count());
    }
}
