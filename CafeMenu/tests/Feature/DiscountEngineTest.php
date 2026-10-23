<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiscountEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::current()->update([
            'accepts_takeaway' => true,
        ]);
    }

    private function checkout(array $items): TestResponse
    {
        return $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => $items,
        ]);
    }

    public function test_percentage_discount_applies_to_whole_order(): void
    {
        $product = Product::factory()->create(['base_price' => 10000]);
        Discount::create([
            'name' => '10% off',
            'type' => 'percentage',
            'value' => 1000,
            'applies_to' => 'order',
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(10000, $order->subtotal);
        $this->assertSame(1000, $order->discount_total);
        $this->assertSame(9000, $order->grand_total);
        $this->assertSame(1, DiscountRedemption::count());
    }

    public function test_fixed_amount_discount_is_clamped_to_the_subtotal(): void
    {
        $product = Product::factory()->create(['base_price' => 10000]);
        Discount::create([
            'name' => 'Big flat discount',
            'type' => 'fixed_amount',
            'value' => 50000,
            'applies_to' => 'order',
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(10000, $order->discount_total);
        $this->assertSame(0, $order->grand_total);
    }

    public function test_item_discount_applies_only_to_the_attached_product(): void
    {
        $discounted = Product::factory()->create(['base_price' => 5000]);
        $other = Product::factory()->create(['base_price' => 3000]);

        $discount = Discount::create([
            'name' => '$10 off espresso',
            'type' => 'item_discount',
            'value' => 1000,
            'applies_to' => 'specific_products',
            'is_active' => true,
        ]);
        $discount->products()->attach($discounted->id);

        $this->checkout([
            ['product_id' => $discounted->id, 'quantity' => 1],
            ['product_id' => $other->id, 'quantity' => 1],
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(1000, $order->discount_total);

        $discountedItem = $order->items()->where('product_id', $discounted->id)->firstOrFail();
        $otherItem = $order->items()->where('product_id', $other->id)->firstOrFail();
        $this->assertSame(1000, $discountedItem->discount_total);
        $this->assertSame(0, $otherItem->discount_total);
    }

    public function test_half_price_discount_applies_to_a_whole_category(): void
    {
        $product = Product::factory()->create(['base_price' => 8000]);

        $discount = Discount::create([
            'name' => 'Half off pastries',
            'type' => 'half_price',
            'applies_to' => 'specific_categories',
            'is_active' => true,
        ]);
        $discount->categories()->attach($product->category_id);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(4000, $order->discount_total);
        $this->assertSame(4000, $order->grand_total);
    }

    public function test_buy_x_get_y_grants_the_cheapest_qualifying_units_free(): void
    {
        $product = Product::factory()->create(['base_price' => 1000]);
        Discount::create([
            'name' => 'Buy 2 get 1',
            'type' => 'buy_x_get_y',
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'applies_to' => 'order',
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 3]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(3000, $order->subtotal);
        $this->assertSame(1000, $order->discount_total);
        $this->assertSame(2000, $order->grand_total);
    }

    public function test_free_item_discount_adds_a_new_fully_discounted_line(): void
    {
        $main = Product::factory()->create(['base_price' => 5000]);
        $freebie = Product::factory()->create(['base_price' => 2000]);

        Discount::create([
            'name' => 'Free cookie',
            'type' => 'free_item',
            'free_item_product_id' => $freebie->id,
            'applies_to' => 'order',
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $main->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(7000, $order->subtotal);
        $this->assertSame(2000, $order->discount_total);
        $this->assertSame(5000, $order->grand_total);

        $freeLine = $order->items()->where('product_id', $freebie->id)->firstOrFail();
        $this->assertSame(2000, $freeLine->line_total);
        $this->assertSame(2000, $freeLine->discount_total);
    }

    public function test_only_the_highest_priority_non_stackable_discount_wins(): void
    {
        $product = Product::factory()->create(['base_price' => 10000]);

        Discount::create([
            'name' => 'Low priority',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'priority' => 1,
            'is_stackable' => false,
            'is_active' => true,
        ]);
        Discount::create([
            'name' => 'High priority',
            'type' => 'fixed_amount',
            'value' => 2000,
            'applies_to' => 'order',
            'priority' => 5,
            'is_stackable' => false,
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(2000, $order->discount_total);
        $this->assertSame(1, DiscountRedemption::count());
    }

    public function test_stackable_discount_layers_on_top_of_the_non_stackable_winner(): void
    {
        $product = Product::factory()->create(['base_price' => 10000]);

        Discount::create([
            'name' => 'Flat off',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'priority' => 10,
            'is_stackable' => false,
            'is_active' => true,
        ]);
        Discount::create([
            'name' => 'Loyalty stack',
            'type' => 'percentage',
            'value' => 500,
            'applies_to' => 'order',
            'priority' => 5,
            'is_stackable' => true,
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        // 10000 - 1000 (flat) = 9000 remaining; 5% of 9000 = 450.
        $this->assertSame(1450, $order->discount_total);
        $this->assertSame(2, DiscountRedemption::count());
    }

    public function test_min_order_amount_blocks_discounts_below_the_threshold(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        Discount::create([
            'name' => 'Big spender discount',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'min_order_amount' => 20000,
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame(0, $order->discount_total);
    }

    public function test_expired_discount_does_not_apply(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        Discount::create([
            'name' => 'Expired',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'ends_at' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $this->assertSame(0, Order::firstOrFail()->discount_total);
    }

    public function test_days_of_week_gate_only_applies_on_matching_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-14')); // a Monday
        $product = Product::factory()->create(['base_price' => 5000]);
        Discount::create([
            'name' => 'Tuesdays only',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'days_of_week' => [2], // Tuesday
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();
        $this->assertSame(0, Order::firstOrFail()->discount_total);

        Carbon::setTestNow();
    }

    public function test_inactive_discount_does_not_apply(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        Discount::create([
            'name' => 'Disabled',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'is_active' => false,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();
        $this->assertSame(0, Order::firstOrFail()->discount_total);
    }

    public function test_usage_limit_is_enforced_across_orders(): void
    {
        $product = Product::factory()->create(['base_price' => 5000]);
        Discount::create([
            'name' => 'First 1 customers',
            'type' => 'fixed_amount',
            'value' => 1000,
            'applies_to' => 'order',
            'usage_limit' => 1,
            'is_active' => true,
        ]);

        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();
        $this->checkout([['product_id' => $product->id, 'quantity' => 1]])->assertOk();

        $orders = Order::orderBy('id')->get();
        $this->assertSame(1000, $orders[0]->discount_total);
        $this->assertSame(0, $orders[1]->discount_total);
        $this->assertSame(1, Discount::sole()->used_count);
    }
}
