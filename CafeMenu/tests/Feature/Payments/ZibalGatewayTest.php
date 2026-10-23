<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Services\Payments\Gateways\PaymentGatewayException;
use App\Services\Payments\Gateways\ZibalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZibalGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::current()->update(['currency' => 'IRT', 'currency_minor_units' => 0]);
        config(['payment.gateways.zibal.merchant' => 'test-merchant']);
    }

    private function placeOrder(int $basePrice = 50000): Order
    {
        $product = Product::factory()->create(['base_price' => $basePrice]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_initiate_stores_track_id_and_returns_redirect(): void
    {
        Http::fake(['*/v1/request' => Http::response(['trackId' => 555111, 'result' => 100, 'message' => 'success'])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);

        $result = (new ZibalGateway)->initiate($payment, 'https://example.test/callback');

        $this->assertSame('GET', $result->method);
        $this->assertStringContainsString('555111', $result->url);
        $this->assertSame('555111', $payment->fresh()->gateway_token);
    }

    public function test_initiate_throws_on_gateway_error(): void
    {
        Http::fake(['*/v1/request' => Http::response(['result' => 102, 'message' => 'merchant not found'])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);

        $this->expectException(PaymentGatewayException::class);

        (new ZibalGateway)->initiate($payment, 'https://example.test/callback');
    }

    public function test_verify_fails_fast_when_success_is_not_one_without_calling_gateway(): void
    {
        Http::fake();

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = '555111';
        $payment->save();

        $result = (new ZibalGateway)->verify(Request::create('/', 'GET', ['trackId' => '555111', 'success' => '0']), $payment);

        $this->assertFalse($result->successful);
        Http::assertNothingSent();
    }

    public function test_verify_succeeds_on_result_100(): void
    {
        Http::fake(['*/v1/verify' => Http::response(['result' => 100, 'refNumber' => 'REF-1'])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = '555111';
        $payment->save();

        $result = (new ZibalGateway)->verify(Request::create('/', 'GET', ['trackId' => '555111', 'success' => '1']), $payment);

        $this->assertTrue($result->successful);
        $this->assertSame('REF-1', $result->gatewayReference);
    }

    public function test_verify_treats_already_verified_result_201_as_success(): void
    {
        Http::fake(['*/v1/verify' => Http::response(['result' => 201, 'refNumber' => 'REF-1'])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = '555111';
        $payment->save();

        $result = (new ZibalGateway)->verify(Request::create('/', 'GET', ['trackId' => '555111', 'success' => '1']), $payment);

        $this->assertTrue($result->successful);
    }

    public function test_verify_fails_on_unknown_result(): void
    {
        Http::fake(['*/v1/verify' => Http::response(['result' => 202])]);

        $order = $this->placeOrder();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'pending']);
        $payment->gateway_token = '555111';
        $payment->save();

        $result = (new ZibalGateway)->verify(Request::create('/', 'GET', ['trackId' => '555111', 'success' => '1']), $payment);

        $this->assertFalse($result->successful);
    }
}
