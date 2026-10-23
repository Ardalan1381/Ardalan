<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_basic_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_checkout_endpoint_is_rate_limited(): void
    {
        $product = Product::factory()->create();

        $payload = [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('checkout.store'), $payload)->assertOk();
        }

        $this->postJson(route('checkout.store'), $payload)->assertStatus(429);
    }

    public function test_order_tracking_endpoint_is_rate_limited(): void
    {
        $product = Product::factory()->create();
        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $order = Order::firstOrFail();

        for ($i = 0; $i < 60; $i++) {
            $this->get(route('orders.show', $order))->assertOk();
        }

        $this->get(route('orders.show', $order))->assertStatus(429);
    }

    public function test_hsts_is_sent_only_for_https_requests_in_production(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
