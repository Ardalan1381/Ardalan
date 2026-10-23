<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->first()->id,
        ]);
    }

    private function placeOrder(int $basePrice = 5000): Order
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

    public function test_owner_can_view_payments_index(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.payments.index'))->assertOk();
    }

    public function test_cashier_can_record_a_full_cash_payment_and_order_becomes_paid(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $response = $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash',
            'amount' => $order->grand_total,
            'status' => 'paid',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(0, $order->balanceDue());
    }

    public function test_partial_payment_leaves_order_payment_status_pending(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash',
            'amount' => 2000,
            'status' => 'paid',
        ]);

        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame(3000, $order->balanceDue());
    }

    public function test_multiple_partial_payments_fully_settle_the_order(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 2000, 'status' => 'paid',
        ]);
        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'card', 'amount' => 3000, 'status' => 'paid',
        ]);

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(2, $order->payments()->count());
    }

    public function test_cannot_record_a_payment_that_exceeds_the_order_total(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $response = $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash',
            'amount' => 9000,
            'status' => 'paid',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, $order->payments()->count());
    }

    public function test_a_second_payment_cannot_push_the_total_over_the_order_amount(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 4000, 'status' => 'paid',
        ]);

        $response = $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 2000, 'status' => 'paid',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame(4000, $order->fresh()->totalPaid());
    }

    public function test_cannot_record_a_zero_or_negative_amount(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 0, 'status' => 'paid',
        ])->assertSessionHasErrors('amount');

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => -100, 'status' => 'paid',
        ])->assertSessionHasErrors('amount');
    }

    public function test_pending_payment_can_be_confirmed_as_paid(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'pay_later', 'amount' => 5000, 'status' => 'pending',
        ]);
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('pending', $order->fresh()->payment_status);

        $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'paid']);

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->paid_at);
    }

    public function test_confirming_a_pending_payment_that_would_overpay_is_rejected(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 5000, 'status' => 'paid',
        ]);
        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'pay_later', 'amount' => 1000, 'status' => 'pending',
        ]);
        $pendingPayment = $order->payments()->where('status', 'pending')->firstOrFail();

        $response = $this->actingAs($cashier)->patch(route('admin.payments.status', $pendingPayment), ['status' => 'paid']);

        $response->assertSessionHasErrors('status');
        $this->assertSame('pending', $pendingPayment->fresh()->status);
    }

    public function test_paid_payment_can_be_refunded(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 5000, 'status' => 'paid',
        ]);
        $payment = $order->payments()->firstOrFail();
        $this->assertSame('paid', $order->fresh()->payment_status);

        $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'refunded']);

        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_cannot_transition_a_refunded_payment(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 5000, 'status' => 'paid',
        ]);
        $payment = $order->payments()->firstOrFail();
        $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'refunded']);

        $response = $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'paid']);

        $response->assertSessionHasErrors('status');
    }

    public function test_cannot_transition_a_failed_payment(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder(5000);

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'card', 'amount' => 5000, 'status' => 'pending',
        ]);
        $payment = $order->payments()->firstOrFail();
        $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'failed']);

        $response = $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'paid']);

        $response->assertSessionHasErrors('status');
    }

    public function test_waiter_cannot_record_payments(): void
    {
        $waiter = $this->userWithRole('waiter');
        $order = $this->placeOrder();

        $this->actingAs($waiter)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 1000, 'status' => 'paid',
        ])->assertForbidden();
    }

    public function test_kitchen_cannot_view_payments(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.payments.index'))->assertForbidden();
    }
}
