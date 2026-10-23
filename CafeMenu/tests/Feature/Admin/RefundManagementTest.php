<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Actions\RecordPaymentAction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundManagementTest extends TestCase
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

    private function orderWithCashPayment(int $price = 10000): Order
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => $price]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        app(RecordPaymentAction::class)->execute($order, ['method' => 'cash', 'amount' => $price, 'status' => 'paid'], $waiter);

        return $order->fresh();
    }

    public function test_owner_can_refund_a_payment_through_the_http_form(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();

        $response = $this->actingAs($owner)->post(route('admin.payments.refund', $payment), [
            'amount' => 4000,
            'reason' => 'Customer complaint',
            'refund_method' => 'cash',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(1, Refund::count());
        $refund = Refund::sole();
        $this->assertSame(4000, $refund->amount);
        $this->assertSame('Customer complaint', $refund->reason);
        $this->assertSame($owner->id, $refund->processed_by_user_id);

        // Original payment is never mutated.
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame(10000, $payment->fresh()->amount);

        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
    }

    public function test_full_refund_through_http_marks_the_order_refunded(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();

        $this->actingAs($owner)->post(route('admin.payments.refund', $payment), [
            'amount' => 10000,
        ])->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_cashier_without_refund_permission_is_forbidden(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();

        $this->assertFalse($cashier->hasPermission('payments.refund'));

        $response = $this->actingAs($cashier)->post(route('admin.payments.refund', $payment), [
            'amount' => 1000,
        ]);

        $response->assertForbidden();
        $this->assertSame(0, Refund::count());
    }

    public function test_refund_amount_exceeding_the_refundable_balance_fails_validation(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();

        $response = $this->actingAs($owner)->post(route('admin.payments.refund', $payment), [
            'amount' => 50000,
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0, Refund::count());
    }

    public function test_order_show_page_displays_existing_refunds(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();

        $this->actingAs($owner)->post(route('admin.payments.refund', $payment), [
            'amount' => 2000,
            'reason' => 'Wrong item',
        ]);

        $response = $this->actingAs($owner)->get(route('admin.orders.show', $order));

        $response->assertOk()->assertSee('Wrong item');
    }
}
