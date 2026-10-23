<?php

namespace Tests\Feature;

use App\Actions\Billing\CreateBillSplitAction;
use App\Actions\Billing\RecordSplitPaymentAction;
use App\Actions\CreateStaffOrderAction;
use App\Actions\ProcessRefundAction;
use App\Actions\RecordPaymentAction;
use App\Actions\Shifts\OpenShiftAction;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function waiter(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'waiter')->first()->id]);
    }

    private function orderWithCashPayment(int $price = 10000): Order
    {
        $waiter = $this->waiter();
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

    public function test_full_refund_marks_the_order_refunded_without_mutating_the_original_payment(): void
    {
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();
        $actor = $this->waiter();

        $refund = app(ProcessRefundAction::class)->execute($payment, ['amount' => 10000, 'reason' => 'Customer complaint'], $actor);

        $this->assertSame(10000, $refund->amount);
        $this->assertSame('completed', $refund->status);
        $this->assertSame('paid', $payment->fresh()->status); // never mutated
        $this->assertSame(10000, $payment->fresh()->amount); // never mutated
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(0, $order->fresh()->totalPaid());
        $this->assertSame(10000, $order->fresh()->balanceDue());
    }

    public function test_partial_refund_marks_the_order_partially_refunded(): void
    {
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();
        $actor = $this->waiter();

        app(ProcessRefundAction::class)->execute($payment, ['amount' => 4000], $actor);

        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertSame(6000, $order->fresh()->totalPaid());
        $this->assertSame(4000, $order->fresh()->balanceDue());
    }

    public function test_refund_amount_cannot_exceed_the_refundable_balance(): void
    {
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();
        $actor = $this->waiter();

        app(ProcessRefundAction::class)->execute($payment, ['amount' => 6000], $actor);

        $this->expectException(ValidationException::class);
        app(ProcessRefundAction::class)->execute($payment->fresh(), ['amount' => 5000], $actor);
    }

    public function test_cannot_refund_a_payment_that_is_not_paid(): void
    {
        $order = $this->orderWithCashPayment(10000);
        $waiter = $this->waiter();
        $pendingPayment = $order->payments()->create(['method' => 'cash', 'amount' => 1000, 'status' => 'pending']);

        $this->expectException(ValidationException::class);
        app(ProcessRefundAction::class)->execute($pendingPayment, ['amount' => 1000], $waiter);
    }

    public function test_refund_amount_must_be_positive(): void
    {
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();
        $actor = $this->waiter();

        $this->expectException(ValidationException::class);
        app(ProcessRefundAction::class)->execute($payment, ['amount' => 0], $actor);
    }

    public function test_a_cash_refund_creates_a_negative_cash_movement_when_the_actor_has_an_open_shift(): void
    {
        $waiter = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();

        app(ProcessRefundAction::class)->execute($payment, ['amount' => 3000, 'refund_method' => 'cash'], $waiter);

        $movement = CashMovement::where('shift_id', $shift->id)->where('type', 'refund')->sole();
        $this->assertSame(-3000, $movement->amount);
    }

    public function test_refund_history_is_preserved_across_multiple_partial_refunds(): void
    {
        $order = $this->orderWithCashPayment(10000);
        $payment = $order->payments()->sole();
        $actor = $this->waiter();

        app(ProcessRefundAction::class)->execute($payment, ['amount' => 2000], $actor);
        app(ProcessRefundAction::class)->execute($payment->fresh(), ['amount' => 3000], $actor);

        $this->assertSame(2, Refund::count());
        $this->assertSame(5000, $payment->fresh()->refundedAmount());
        $this->assertSame(5000, $payment->fresh()->refundableAmount());
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
    }

    public function test_refunding_a_split_payment_resyncs_the_part(): void
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $productA = Product::factory()->create(['base_price' => 6000]);
        $productB = Product::factory()->create(['base_price' => 4000]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1],
                ['product_id' => $productB->id, 'quantity' => 1],
            ],
        ], $waiter);

        $items = $order->items()->orderBy('id')->get();
        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $waiter);

        $alicePart = $split->parts()->orderBy('id')->first();
        $payment = app(RecordSplitPaymentAction::class)->execute($alicePart, ['method' => 'cash', 'amount' => 6000, 'status' => 'paid'], $waiter);
        $this->assertSame('paid', $alicePart->fresh()->status);

        app(ProcessRefundAction::class)->execute($payment, ['amount' => 6000], $waiter);

        $this->assertSame('pending', $alicePart->fresh()->status);
        $this->assertSame(0, $alicePart->fresh()->amount_paid);
    }
}
