<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AUD-03: retried checkouts must never create duplicate orders.
 */
class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Product $product, array $overrides = []): array
    {
        return array_merge([
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'idempotency_key' => '0b7d3c3a-6c6e-4f0e-8a8e-3f7f6d3d9a11',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], $overrides);
    }

    public function test_replaying_the_same_checkout_returns_the_same_order(): void
    {
        $product = Product::factory()->create();

        $first = $this->postJson(route('checkout.store'), $this->payload($product))->assertOk()->json('redirect');
        $second = $this->postJson(route('checkout.store'), $this->payload($product))->assertOk()->json('redirect');

        $this->assertSame($first, $second);
        $this->assertSame(1, Order::count());
    }

    public function test_a_replay_still_succeeds_after_ordering_was_switched_off(): void
    {
        $product = Product::factory()->create();
        $first = $this->postJson(route('checkout.store'), $this->payload($product))->assertOk()->json('redirect');

        BusinessSetting::current()->update(['ordering_enabled' => false]);

        $this->assertSame($first, $this->postJson(route('checkout.store'), $this->payload($product))->assertOk()->json('redirect'));
    }

    public function test_reusing_a_key_for_a_different_cart_is_rejected(): void
    {
        $product = Product::factory()->create();
        $this->postJson(route('checkout.store'), $this->payload($product))->assertOk();

        $this->postJson(route('checkout.store'), $this->payload($product, ['items' => [['product_id' => $product->id, 'quantity' => 5]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertSame(1, Order::count());
    }

    public function test_different_keys_create_different_orders(): void
    {
        $product = Product::factory()->create();

        $this->postJson(route('checkout.store'), $this->payload($product))->assertOk();
        $this->postJson(route('checkout.store'), $this->payload($product, ['idempotency_key' => 'f3c1e2d4-1111-4222-8333-944455556666']))->assertOk();

        $this->assertSame(2, Order::count());
    }

    public function test_a_malformed_key_is_rejected_and_requests_without_a_key_still_work(): void
    {
        $product = Product::factory()->create();

        $this->postJson(route('checkout.store'), $this->payload($product, ['idempotency_key' => 'not-a-uuid']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->postJson(route('checkout.store'), $this->payload($product, ['idempotency_key' => null]))->assertOk();
        $this->assertSame(1, Order::count());
    }
}
