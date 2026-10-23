<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayOnlineFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BusinessSetting::current()->update([
            'currency' => 'IRT',
            'currency_minor_units' => 0,
            'enabled_online_gateways' => ['zarinpal'],
        ]);
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
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

    public function test_order_tracking_page_shows_pay_online_button_when_gateway_available(): void
    {
        $order = $this->placeOrder();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee(route('payments.initiate', [$order, 'zarinpal']), false);
    }

    public function test_order_tracking_page_hides_pay_online_when_no_gateway_enabled(): void
    {
        BusinessSetting::current()->update(['enabled_online_gateways' => []]);
        $order = $this->placeOrder();

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertDontSee('payments/callback', false);
    }

    public function test_initiate_creates_pending_payment_and_redirects_to_gateway(): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'AUTH-1'],
                'errors' => [],
            ]),
        ]);

        $order = $this->placeOrder();

        $response = $this->get(route('payments.initiate', [$order, 'zarinpal']));

        $response->assertRedirect('https://www.zarinpal.com/pg/StartPay/AUTH-1');
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'method' => 'online',
            'gateway' => 'zarinpal',
            'gateway_token' => 'AUTH-1',
            'status' => 'pending',
        ]);
    }

    public function test_initiate_rejects_unavailable_gateway(): void
    {
        $order = $this->placeOrder();

        $this->get(route('payments.initiate', [$order, 'mellat']))->assertNotFound();
    }

    public function test_initiate_redirects_back_when_nothing_is_due(): void
    {
        $order = $this->placeOrder();
        $order->payments()->create(['method' => 'cash', 'amount' => $order->grand_total, 'status' => 'paid']);
        $order->syncPaymentStatus();

        $response = $this->get(route('payments.initiate', [$order, 'zarinpal']));

        $response->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseMissing('payments', ['order_id' => $order->id, 'gateway' => 'zarinpal']);
    }

    public function test_double_initiate_supersedes_the_first_pending_payment(): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::sequence()
                ->push(['data' => ['code' => 100, 'authority' => 'AUTH-1'], 'errors' => []])
                ->push(['data' => ['code' => 100, 'authority' => 'AUTH-2'], 'errors' => []]),
        ]);

        $order = $this->placeOrder();

        $this->get(route('payments.initiate', [$order, 'zarinpal']));
        $this->get(route('payments.initiate', [$order, 'zarinpal']));

        $this->assertDatabaseHas('payments', ['gateway_token' => 'AUTH-1', 'status' => 'failed']);
        $this->assertDatabaseHas('payments', ['gateway_token' => 'AUTH-2', 'status' => 'pending']);
        $this->assertSame(1, Payment::where('order_id', $order->id)->where('status', 'pending')->count());
    }

    public function test_successful_callback_marks_payment_paid_and_order_paid(): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'AUTH-1'], 'errors' => []]),
            '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 55555], 'errors' => []]),
        ]);

        $order = $this->placeOrder();
        $this->get(route('payments.initiate', [$order, 'zarinpal']));

        $response = $this->get(route('payments.callback', 'zarinpal').'?Authority=AUTH-1&Status=OK');

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['gateway_token' => 'AUTH-1', 'status' => 'paid', 'reference' => '55555']);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_failed_callback_marks_payment_failed(): void
    {
        Http::fake(['*/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'AUTH-1'], 'errors' => []])]);

        $order = $this->placeOrder();
        $this->get(route('payments.initiate', [$order, 'zarinpal']));

        $response = $this->get(route('payments.callback', 'zarinpal').'?Authority=AUTH-1&Status=NOK');

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['gateway_token' => 'AUTH-1', 'status' => 'failed']);
    }

    public function test_unknown_token_renders_verification_failed_without_touching_any_payment(): void
    {
        $response = $this->get(route('payments.callback', 'zarinpal').'?Authority=does-not-exist&Status=OK');

        $response->assertOk();
        $response->assertSeeText(__('payments.status_page.verification_failed.title'));
    }

    public function test_duplicate_callback_delivery_does_not_re_verify_or_double_process(): void
    {
        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response(['data' => ['code' => 100, 'authority' => 'AUTH-1'], 'errors' => []]),
            '*/pg/v4/payment/verify.json' => Http::response(['data' => ['code' => 100, 'ref_id' => 55555], 'errors' => []]),
        ]);

        $order = $this->placeOrder();
        $this->get(route('payments.initiate', [$order, 'zarinpal']));

        $this->get(route('payments.callback', 'zarinpal').'?Authority=AUTH-1&Status=OK')->assertOk();
        Http::assertSentCount(2); // request + verify

        // Second delivery of the same callback must not call verify again.
        $this->get(route('payments.callback', 'zarinpal').'?Authority=AUTH-1&Status=OK')->assertOk();
        Http::assertSentCount(2);

        $this->assertSame(1, Payment::where('gateway_token', 'AUTH-1')->where('status', 'paid')->count());
    }
}
