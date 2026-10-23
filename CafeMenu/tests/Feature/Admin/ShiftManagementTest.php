<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Actions\Shifts\OpenShiftAction;
use App\Models\CashMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftManagementTest extends TestCase
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

    // ---- Opening a shift ----

    public function test_a_waiter_can_open_a_shift(): void
    {
        $waiter = $this->userWithRole('waiter');

        $response = $this->actingAs($waiter)->post(route('admin.shifts.store'), ['opening_cash_amount' => 50000]);

        $shift = Shift::firstOrFail();
        $response->assertRedirect(route('admin.shifts.show', $shift));
        $this->assertSame('open', $shift->status);
        $this->assertSame($waiter->id, $shift->user_id);
        $this->assertSame(50000, $shift->opening_cash_amount);
    }

    public function test_a_waiter_cannot_open_a_second_shift_while_one_is_open(): void
    {
        $waiter = $this->userWithRole('waiter');
        app(OpenShiftAction::class)->execute($waiter, 50000);

        $response = $this->actingAs($waiter)->post(route('admin.shifts.store'), ['opening_cash_amount' => 20000]);

        $response->assertRedirect(route('admin.shifts.index'));
        $response->assertSessionHasErrors('shift');
        $this->assertSame(1, Shift::count());
    }

    // ---- Manual cash movements ----

    public function test_a_waiter_can_record_a_manual_cash_movement_with_a_reason(): void
    {
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $response = $this->actingAs($waiter)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'cash_out',
            'amount' => 5000,
            'reason' => 'Petty cash for supplies',
        ]);

        $response->assertRedirect(route('admin.shifts.show', $shift));
        $movement = CashMovement::where('shift_id', $shift->id)->sole();
        $this->assertSame('cash_out', $movement->type);
        $this->assertSame(-5000, $movement->amount);
        $this->assertSame('Petty cash for supplies', $movement->reason);
        $this->assertSame($waiter->id, $movement->created_by_user_id);
    }

    public function test_recording_a_manual_cash_movement_without_a_reason_is_rejected(): void
    {
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $response = $this->actingAs($waiter)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'cash_in',
            'amount' => 5000,
        ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(0, CashMovement::count());
    }

    public function test_an_adjustment_cash_movement_uses_the_explicit_sign_selector(): void
    {
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $this->actingAs($waiter)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'adjustment',
            'sign' => '-',
            'amount' => 1500,
            'reason' => 'Drawer recount correction',
        ]);

        $movement = CashMovement::where('shift_id', $shift->id)->sole();
        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(-1500, $movement->amount);
    }

    // ---- Closing a shift ----

    public function test_a_waiter_can_close_their_own_shift_and_the_difference_is_computed(): void
    {
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $this->actingAs($waiter)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'cash_in',
            'amount' => 10000,
            'reason' => 'Extra float',
        ]);
        $this->actingAs($waiter)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'cash_out',
            'amount' => 2000,
            'reason' => 'Supplies',
        ]);

        $response = $this->actingAs($waiter)->patch(route('admin.shifts.close', $shift), [
            'closing_cash_amount' => 57000,
        ]);

        $response->assertRedirect(route('admin.shifts.show', $shift));
        $closed = $shift->fresh();
        $this->assertSame('closed', $closed->status);
        $this->assertSame(58000, $closed->expected_cash_amount); // 50000 + 10000 - 2000
        $this->assertSame(57000, $closed->closing_cash_amount);
        $this->assertSame(-1000, $closed->cash_difference);
    }

    // ---- Kitchen has no shift permissions at all ----

    public function test_a_kitchen_user_is_forbidden_throughout(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $this->actingAs($kitchen)->get(route('admin.shifts.index'))->assertForbidden();
        $this->actingAs($kitchen)->post(route('admin.shifts.store'), ['opening_cash_amount' => 10000])->assertForbidden();
        $this->actingAs($kitchen)->get(route('admin.shifts.show', $shift))->assertForbidden();
        $this->actingAs($kitchen)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'cash_in', 'amount' => 1000, 'reason' => 'x',
        ])->assertForbidden();
        $this->actingAs($kitchen)->patch(route('admin.shifts.close', $shift), ['closing_cash_amount' => 50000])->assertForbidden();
    }

    // ---- A waiter cannot view or act on another waiter's shift ----

    public function test_a_waiter_cannot_view_or_close_a_different_waiters_shift(): void
    {
        $waiterA = $this->userWithRole('waiter');
        $waiterB = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiterA, 50000);

        $this->actingAs($waiterB)->get(route('admin.shifts.show', $shift))->assertForbidden();
        $this->actingAs($waiterB)->patch(route('admin.shifts.close', $shift), ['closing_cash_amount' => 50000])->assertForbidden();
        $this->actingAs($waiterB)->post(route('admin.shifts.record-cash-movement', $shift), [
            'type' => 'cash_in', 'amount' => 1000, 'reason' => 'x',
        ])->assertForbidden();
    }

    // ---- A manager with shifts.view_all / shifts.manage_all can act on anyone's shift ----

    public function test_a_manager_can_view_and_close_another_staff_members_shift(): void
    {
        $manager = $this->userWithRole('manager');
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $this->actingAs($manager)->get(route('admin.shifts.show', $shift))->assertOk();

        $response = $this->actingAs($manager)->patch(route('admin.shifts.close', $shift), ['closing_cash_amount' => 50000]);
        $response->assertRedirect(route('admin.shifts.show', $shift));
        $this->assertSame('closed', $shift->fresh()->status);
    }

    public function test_the_index_page_shows_all_staff_shifts_only_to_users_with_view_all(): void
    {
        $manager = $this->userWithRole('manager');
        $waiter = $this->userWithRole('waiter');
        app(OpenShiftAction::class)->execute($waiter, 50000);

        $this->actingAs($manager)->get(route('admin.shifts.index'))
            ->assertOk()
            ->assertSee(__('admin.shifts.all_staff_shifts'));

        $this->actingAs($waiter)->get(route('admin.shifts.index'))
            ->assertOk()
            ->assertDontSee(__('admin.shifts.all_staff_shifts'));
    }

    // ---- Close-confirmation warns about (but never blocks on) unpaid open sessions ----

    public function test_close_confirmation_shows_but_does_not_block_on_open_unpaid_sessions(): void
    {
        $waiter = $this->userWithRole('waiter');
        $shift = app(OpenShiftAction::class)->execute($waiter, 50000);

        $table = Table::factory()->create();
        $product = Product::factory()->create(['base_price' => 5000]);
        app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ], $waiter);

        $response = $this->actingAs($waiter)->get(route('admin.shifts.show', $shift));

        $response->assertOk();
        $response->assertSee(__('admin.shifts.open_unpaid_sessions_warning', ['count' => 1]));

        // The warning is informational only — closing must still succeed.
        $closeResponse = $this->actingAs($waiter)->patch(route('admin.shifts.close', $shift), ['closing_cash_amount' => 50000]);
        $closeResponse->assertRedirect(route('admin.shifts.show', $shift));
        $this->assertSame('closed', $shift->fresh()->status);
    }
}
