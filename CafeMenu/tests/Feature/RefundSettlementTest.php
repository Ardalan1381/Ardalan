<?php

namespace Tests\Feature;

use App\Actions\Billing\CreateBillSplitAction;
use App\Actions\Billing\RecordSplitPaymentAction;
use App\Actions\CreateStaffOrderAction;
use App\Actions\ProcessRefundAction;
use App\Actions\RecordPaymentAction;
use App\Actions\ResolveRefundAction;
use App\Actions\UpdatePaymentStatusAction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * AUD-09: a refund that has to happen outside the app must not be reported
 * as completed just because a record was written. AUD-08: split parts stay
 * reconciled with their payments through every payment state change.
 */
class RefundSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->owner = User::factory()->create(['role_id' => Role::where('name', 'owner')->first()->id]);
    }

    private function order(array $prices): Order
    {
        $table = Table::factory()->create();

        return app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => collect($prices)->map(fn (int $price) => [
                'product_id' => Product::factory()->create(['base_price' => $price])->id,
                'quantity' => 1,
            ])->all(),
        ], $this->owner);
    }

    private function paidCardPayment(Order $order): Payment
    {
        return app(RecordPaymentAction::class)->execute($order, ['method' => 'card', 'amount' => $order->grand_total, 'status' => 'paid'], $this->owner);
    }

    public function test_card_refund_is_pending_until_confirmed_and_does_not_yet_reduce_the_paid_total(): void
    {
        $order = $this->order([10000]);
        $payment = $this->paidCardPayment($order);

        $refund = app(ProcessRefundAction::class)->execute($payment, ['amount' => 4000], $this->owner);

        $this->assertSame('pending', $refund->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame(10000, $order->fresh()->totalPaid());
        // ...but the amount is reserved: it cannot be refunded a second time.
        $this->assertSame(6000, $payment->fresh()->refundableAmount());

        app(ResolveRefundAction::class)->execute($refund, 'completed', $this->owner, 'POS-REV-991');

        $refund->refresh();
        $this->assertSame('completed', $refund->status);
        $this->assertSame('POS-REV-991', $refund->external_reference);
        $this->assertSame($this->owner->id, $refund->resolved_by_user_id);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertSame(6000, $order->fresh()->totalPaid());
    }

    public function test_a_failed_refund_releases_the_reserved_amount(): void
    {
        $order = $this->order([10000]);
        $payment = $this->paidCardPayment($order);
        $refund = app(ProcessRefundAction::class)->execute($payment, ['amount' => 10000, 'refund_method' => 'store_credit'], $this->owner);

        $this->assertSame(0, $payment->fresh()->refundableAmount());

        app(ResolveRefundAction::class)->execute($refund, 'failed', $this->owner, note: 'Customer declined');

        $this->assertSame('failed', $refund->fresh()->status);
        $this->assertSame(10000, $payment->fresh()->refundableAmount());
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_cash_refunds_complete_immediately(): void
    {
        $order = $this->order([10000]);
        $payment = $this->paidCardPayment($order);

        $refund = app(ProcessRefundAction::class)->execute($payment, ['amount' => 10000, 'refund_method' => 'cash'], $this->owner);

        $this->assertSame('completed', $refund->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_an_already_resolved_refund_cannot_be_resolved_again(): void
    {
        $order = $this->order([10000]);
        $refund = app(ProcessRefundAction::class)->execute($this->paidCardPayment($order), ['amount' => 1000], $this->owner);
        app(ResolveRefundAction::class)->execute($refund, 'completed', $this->owner);

        $this->expectException(ValidationException::class);
        app(ResolveRefundAction::class)->execute($refund->fresh(), 'failed', $this->owner);
    }

    public function test_resolve_endpoint_requires_the_refund_permission(): void
    {
        $order = $this->order([10000]);
        $refund = app(ProcessRefundAction::class)->execute($this->paidCardPayment($order), ['amount' => 1000], $this->owner);
        $kitchen = User::factory()->create(['role_id' => Role::where('name', 'kitchen')->first()->id]);

        $this->actingAs($kitchen)->patch(route('admin.refunds.resolve', $refund), ['outcome' => 'completed'])->assertForbidden();
        $this->assertSame('pending', $refund->fresh()->status);

        $this->actingAs($this->owner)->patch(route('admin.refunds.resolve', $refund), ['outcome' => 'completed', 'external_reference' => 'X1'])
            ->assertRedirect(route('admin.orders.show', $order));
        $this->assertSame('completed', $refund->fresh()->status);
    }

    public function test_order_page_shows_pending_refund_with_confirmation_controls(): void
    {
        $order = $this->order([10000]);
        app(ProcessRefundAction::class)->execute($this->paidCardPayment($order), ['amount' => 1000], $this->owner);

        $this->actingAs($this->owner)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('data-refund-status="pending"', false)
            ->assertSee(route('admin.refunds.resolve', $order->payments()->first()->refunds()->first()), false);
    }

    public function test_split_part_is_reconciled_when_a_pending_split_payment_is_confirmed_later(): void
    {
        $order = $this->order([6000, 4000]);
        $items = $order->items()->orderBy('id')->get();
        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $this->owner);
        [$alice, $bob] = $split->parts()->orderBy('id')->get()->all();

        $pending = app(RecordSplitPaymentAction::class)->execute($alice, ['method' => 'card', 'amount' => 6000, 'status' => 'pending'], $this->owner);
        $this->assertSame(0, $alice->fresh()->amount_paid);

        app(UpdatePaymentStatusAction::class)->execute($pending, 'paid', $this->owner);
        $this->assertSame(6000, $alice->fresh()->amount_paid);
        $this->assertSame('paid', $alice->fresh()->status);

        app(RecordSplitPaymentAction::class)->execute($bob, ['method' => 'cash', 'amount' => 4000, 'status' => 'paid'], $this->owner);
        $this->assertSame('settled', $split->fresh()->status);

        // Conservation: parts sum to the order total, and paid-in parts
        // match the money the order counts as received.
        $this->assertSame($order->grand_total, (int) $split->parts()->sum('amount_due'));
        $this->assertSame($order->fresh()->totalPaid(), (int) $split->parts()->sum('amount_paid'));

        // A completed refund on one part re-opens that part and the split.
        $refund = app(ProcessRefundAction::class)->execute($pending->fresh(), ['amount' => 6000, 'refund_method' => 'cash'], $this->owner);
        $this->assertSame('completed', $refund->status);
        $this->assertSame('pending', $alice->fresh()->status);
        $this->assertSame('open', $split->fresh()->status);
        $this->assertSame($order->fresh()->totalPaid(), (int) $split->parts()->sum('amount_paid'));
    }

    public function test_a_failed_split_payment_leaves_the_part_unpaid(): void
    {
        $order = $this->order([5000]);
        $item = $order->items()->sole();
        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [['label' => 'All', 'order_item_ids' => [$item->id]]], $this->owner);
        $part = $split->parts()->sole();

        $pending = app(RecordSplitPaymentAction::class)->execute($part, ['method' => 'card', 'amount' => 5000, 'status' => 'pending'], $this->owner);
        app(UpdatePaymentStatusAction::class)->execute($pending, 'failed', $this->owner);

        $this->assertSame(0, $part->fresh()->amount_paid);
        $this->assertSame('pending', $part->fresh()->status);
    }
}
