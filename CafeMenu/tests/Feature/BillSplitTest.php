<?php

namespace Tests\Feature;

use App\Actions\Billing\CreateBillSplitAction;
use App\Actions\Billing\RecordSplitPaymentAction;
use App\Actions\CreateStaffOrderAction;
use App\Models\BillSplitPartItem;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use App\Models\Product;

class BillSplitTest extends TestCase
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

    private function orderWithTwoItems(): \App\Models\Order
    {
        $waiter = $this->waiter();
        $table = Table::factory()->create();
        $productA = Product::factory()->create(['base_price' => 6000]);
        $productB = Product::factory()->create(['base_price' => 4000]);

        return app(CreateStaffOrderAction::class)->execute([
            'type' => 'dine_in',
            'table_id' => $table->id,
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 1],
                ['product_id' => $productB->id, 'quantity' => 1],
            ],
        ], $waiter);
    }

    public function test_by_item_split_allocates_each_part_its_own_items_total(): void
    {
        $order = $this->orderWithTwoItems();
        $items = $order->items()->orderBy('id')->get();
        $actor = $this->waiter();

        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $actor);

        $parts = $split->parts()->orderBy('id')->get();
        $this->assertSame(6000, $parts[0]->amount_due);
        $this->assertSame(4000, $parts[1]->amount_due);
        $this->assertSame(10000, $parts->sum('amount_due'));
    }

    public function test_even_split_sums_exactly_to_the_grand_total_despite_rounding(): void
    {
        $order = $this->orderWithTwoItems(); // grand_total 10000
        $actor = $this->waiter();

        $split = app(CreateBillSplitAction::class)->execute($order, 'even', [
            ['label' => 'Part 1'],
            ['label' => 'Part 2'],
            ['label' => 'Part 3'],
        ], $actor);

        $parts = $split->parts;
        $this->assertSame(10000, $parts->sum('amount_due'));
        $this->assertCount(3, $parts);
    }

    public function test_by_amount_split_requires_amounts_to_sum_exactly_to_the_total(): void
    {
        $order = $this->orderWithTwoItems();
        $actor = $this->waiter();

        $this->expectException(ValidationException::class);
        app(CreateBillSplitAction::class)->execute($order, 'by_amount', [
            ['label' => 'Alice', 'amount' => 3000],
            ['label' => 'Bob', 'amount' => 3000],
        ], $actor);
    }

    public function test_the_same_order_item_cannot_be_assigned_to_two_parts(): void
    {
        $order = $this->orderWithTwoItems();
        $items = $order->items()->orderBy('id')->get();
        $actor = $this->waiter();

        $this->expectException(ValidationException::class);
        app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id, $items[1]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $actor);
    }

    public function test_the_database_constraint_blocks_a_duplicate_assignment_even_across_separate_splits(): void
    {
        $order = $this->orderWithTwoItems();
        $items = $order->items()->orderBy('id')->get();

        BillSplitPartItem::create([
            'bill_split_part_id' => \App\Models\BillSplitPart::create([
                'bill_split_id' => \App\Models\BillSplit::create([
                    'order_id' => $order->id,
                    'split_type' => 'by_item',
                    'status' => 'open',
                    'created_by_user_id' => $this->waiter()->id,
                ])->id,
                'label' => 'Existing',
                'amount_due' => 6000,
            ])->id,
            'order_item_id' => $items[0]->id,
            'quantity_allocated' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        BillSplitPartItem::create([
            'bill_split_part_id' => \App\Models\BillSplitPart::create([
                'bill_split_id' => \App\Models\BillSplit::where('order_id', $order->id)->first()->id,
                'label' => 'Duplicate attempt',
                'amount_due' => 1000,
            ])->id,
            'order_item_id' => $items[0]->id,
            'quantity_allocated' => 1,
        ]);
    }

    public function test_recording_a_full_payment_against_a_part_settles_it(): void
    {
        $order = $this->orderWithTwoItems();
        $items = $order->items()->orderBy('id')->get();
        $actor = $this->waiter();

        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $actor);

        $parts = $split->parts()->orderBy('id')->get();
        app(RecordSplitPaymentAction::class)->execute($parts[0], ['method' => 'cash', 'amount' => 6000, 'status' => 'paid'], $actor);

        $this->assertSame('paid', $parts[0]->fresh()->status);
        $this->assertSame('open', $split->fresh()->status);
    }

    public function test_the_split_settles_once_every_part_is_paid(): void
    {
        $order = $this->orderWithTwoItems();
        $items = $order->items()->orderBy('id')->get();
        $actor = $this->waiter();

        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $actor);

        $parts = $split->parts()->orderBy('id')->get();
        app(RecordSplitPaymentAction::class)->execute($parts[0], ['method' => 'cash', 'amount' => 6000, 'status' => 'paid'], $actor);
        app(RecordSplitPaymentAction::class)->execute($parts[1], ['method' => 'cash', 'amount' => 4000, 'status' => 'paid'], $actor);

        $this->assertSame('settled', $split->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_payment_exceeding_a_parts_balance_is_rejected(): void
    {
        $order = $this->orderWithTwoItems();
        $items = $order->items()->orderBy('id')->get();
        $actor = $this->waiter();

        $split = app(CreateBillSplitAction::class)->execute($order, 'by_item', [
            ['label' => 'Alice', 'order_item_ids' => [$items[0]->id]],
            ['label' => 'Bob', 'order_item_ids' => [$items[1]->id]],
        ], $actor);

        $part = $split->parts()->orderBy('id')->first();

        $this->expectException(ValidationException::class);
        app(RecordSplitPaymentAction::class)->execute($part, ['method' => 'cash', 'amount' => 9999999, 'status' => 'paid'], $actor);
    }

    public function test_cannot_split_an_order_that_already_has_a_whole_order_payment(): void
    {
        $order = $this->orderWithTwoItems();
        $actor = $this->waiter();
        app(\App\Actions\RecordPaymentAction::class)->execute($order, ['method' => 'cash', 'amount' => 10000, 'status' => 'paid'], $actor);

        $this->expectException(ValidationException::class);
        app(CreateBillSplitAction::class)->execute($order, 'even', [
            ['label' => 'Alice'],
            ['label' => 'Bob'],
        ], $actor);
    }
}
