<?php

namespace Tests\Feature;

use App\Actions\CreateStaffOrderAction;
use App\Actions\RecordPaymentAction;
use App\Actions\Shifts\CloseShiftAction;
use App\Actions\Shifts\OpenShiftAction;
use App\Actions\Shifts\RecordCashMovementAction;
use App\Actions\UpdatePaymentStatusAction;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShiftEngineTest extends TestCase
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

    public function test_opening_a_shift_records_opening_cash(): void
    {
        $user = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($user, 50000);

        $this->assertSame('open', $shift->status);
        $this->assertSame(50000, $shift->opening_cash_amount);
    }

    public function test_a_user_cannot_open_a_second_shift_while_one_is_already_open(): void
    {
        $user = $this->waiter();
        app(OpenShiftAction::class)->execute($user, 50000);

        $this->expectException(ValidationException::class);
        app(OpenShiftAction::class)->execute($user, 20000);
    }

    public function test_manual_cash_movement_requires_a_reason(): void
    {
        $user = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($user, 50000);

        $this->expectException(ValidationException::class);
        app(RecordCashMovementAction::class)->execute($shift, 'cash_out', -5000, $user);
    }

    public function test_manual_cash_movement_with_a_reason_succeeds(): void
    {
        $user = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($user, 50000);

        $movement = app(RecordCashMovementAction::class)->execute($shift, 'cash_out', -5000, $user, reason: 'Petty cash for supplies');

        $this->assertSame(-5000, $movement->amount);
        $this->assertSame('cash_out', $movement->type);
    }

    public function test_cannot_record_a_cash_movement_against_a_closed_shift(): void
    {
        $user = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($user, 50000);
        app(CloseShiftAction::class)->execute($shift, 50000);

        $this->expectException(ValidationException::class);
        app(RecordCashMovementAction::class)->execute($shift->fresh(), 'cash_in', 1000, $user, reason: 'Top-up');
    }

    public function test_closing_a_shift_computes_expected_cash_and_the_difference(): void
    {
        $user = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($user, 50000);
        app(RecordCashMovementAction::class)->execute($shift, 'cash_in', 10000, $user, reason: 'Extra float');
        app(RecordCashMovementAction::class)->execute($shift, 'cash_out', -2000, $user, reason: 'Supplies');

        $closed = app(CloseShiftAction::class)->execute($shift->fresh(), 57000);

        $this->assertSame('closed', $closed->status);
        $this->assertSame(58000, $closed->expected_cash_amount); // 50000 + 10000 - 2000
        $this->assertSame(57000, $closed->closing_cash_amount);
        $this->assertSame(-1000, $closed->cash_difference);
    }

    public function test_closing_an_already_closed_shift_is_rejected(): void
    {
        $user = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($user, 50000);
        app(CloseShiftAction::class)->execute($shift, 50000);

        $this->expectException(ValidationException::class);
        app(CloseShiftAction::class)->execute($shift->fresh(), 50000);
    }

    public function test_recording_a_cash_payment_creates_a_sale_cash_movement_when_the_actor_has_an_open_shift(): void
    {
        $waiter = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        app(RecordPaymentAction::class)->execute($order, ['method' => 'cash', 'amount' => 5000, 'status' => 'paid'], $waiter);

        $this->assertSame(1, CashMovement::where('shift_id', $shift->id)->where('type', 'sale')->count());
        $movement = CashMovement::where('shift_id', $shift->id)->where('type', 'sale')->sole();
        $this->assertSame(5000, $movement->amount);
    }

    public function test_recording_a_cash_payment_is_a_no_op_when_the_actor_has_no_open_shift(): void
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        // No open shift for this waiter — must not throw, must create no movement.
        $payment = app(RecordPaymentAction::class)->execute($order, ['method' => 'cash', 'amount' => 5000, 'status' => 'paid'], $waiter);

        $this->assertSame('paid', $payment->status);
        $this->assertSame(0, CashMovement::count());
    }

    public function test_a_card_payment_never_creates_a_cash_movement_even_with_an_open_shift(): void
    {
        $waiter = $this->waiter();
        app(OpenShiftAction::class)->execute($waiter, 50000);
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        app(RecordPaymentAction::class)->execute($order, ['method' => 'card', 'amount' => 5000, 'status' => 'paid'], $waiter);

        $this->assertSame(0, CashMovement::count());
    }

    public function test_marking_a_pending_cash_payment_paid_via_status_action_creates_a_sale_movement(): void
    {
        $waiter = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $payment = app(RecordPaymentAction::class)->execute($order, ['method' => 'cash', 'amount' => 5000, 'status' => 'pending'], $waiter);
        app(UpdatePaymentStatusAction::class)->execute($payment, 'paid', $waiter);

        $this->assertSame(1, CashMovement::where('type', 'sale')->where('shift_id', $shift->id)->count());
    }

    public function test_refunding_a_cash_payment_via_status_action_creates_a_negative_refund_movement(): void
    {
        $waiter = $this->waiter();
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);
        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);

        $order = app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $payment = app(RecordPaymentAction::class)->execute($order, ['method' => 'cash', 'amount' => 5000, 'status' => 'paid'], $waiter);
        app(UpdatePaymentStatusAction::class)->execute($payment->fresh(), 'refunded', $waiter);

        $refundMovement = CashMovement::where('type', 'refund')->where('shift_id', $shift->id)->sole();
        $this->assertSame(-5000, $refundMovement->amount);
    }
}
