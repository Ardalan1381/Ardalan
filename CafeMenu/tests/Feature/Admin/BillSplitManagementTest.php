<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateStaffOrderAction;
use App\Models\BillSplit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillSplitManagementTest extends TestCase
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

    private function orderWithTwoItems(User $waiter): Order
    {
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

    public function test_by_item_split_created_through_http_allocates_amounts_correctly(): void
    {
        $waiter = $this->userWithRole('waiter');
        $order = $this->orderWithTwoItems($waiter);
        $items = $order->items()->orderBy('id')->get();

        $response = $this->actingAs($waiter)->post(route('admin.orders.bill-split.store', $order), [
            'split_type' => 'by_item',
            'parts' => [
                1 => ['label' => 'Alice'],
                2 => ['label' => 'Bob'],
                3 => ['label' => ''],
                4 => ['label' => ''],
            ],
            'item_assignment' => [
                $items[0]->id => '1',
                $items[1]->id => '2',
            ],
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $split = BillSplit::where('order_id', $order->id)->sole();
        $this->assertSame('by_item', $split->split_type);

        $parts = $split->parts()->orderBy('id')->get();
        $this->assertCount(2, $parts);
        $this->assertSame('Alice', $parts[0]->label);
        $this->assertSame(6000, $parts[0]->amount_due);
        $this->assertSame('Bob', $parts[1]->label);
        $this->assertSame(4000, $parts[1]->amount_due);
    }

    public function test_recording_a_payment_against_a_part_settles_it(): void
    {
        $waiter = $this->userWithRole('waiter');
        $order = $this->orderWithTwoItems($waiter);
        $items = $order->items()->orderBy('id')->get();

        $this->actingAs($waiter)->post(route('admin.orders.bill-split.store', $order), [
            'split_type' => 'by_item',
            'parts' => [
                1 => ['label' => 'Alice'],
                2 => ['label' => 'Bob'],
            ],
            'item_assignment' => [
                $items[0]->id => '1',
                $items[1]->id => '2',
            ],
        ]);

        $split = BillSplit::where('order_id', $order->id)->sole();
        $alicePart = $split->parts()->where('label', 'Alice')->sole();

        $response = $this->actingAs($waiter)->post(route('admin.bill-split-parts.payments.store', $alicePart), [
            'method' => 'cash',
            'amount' => 6000,
            'status' => 'paid',
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('paid', $alicePart->fresh()->status);
        $this->assertSame(6000, $alicePart->fresh()->amount_paid);
    }

    public function test_even_split_created_through_http_sums_to_the_grand_total(): void
    {
        $waiter = $this->userWithRole('waiter');
        $order = $this->orderWithTwoItems($waiter); // grand_total 10000

        $response = $this->actingAs($waiter)->post(route('admin.orders.bill-split.store', $order), [
            'split_type' => 'even',
            'parts' => [
                1 => ['label' => 'Part 1'],
                2 => ['label' => 'Part 2'],
            ],
        ]);

        $response->assertRedirect(route('admin.orders.show', $order));

        $split = BillSplit::where('order_id', $order->id)->sole();
        $this->assertSame(10000, $split->parts->sum('amount_due'));
    }

    public function test_kitchen_role_cannot_create_a_bill_split(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $waiter = $this->userWithRole('waiter');
        $order = $this->orderWithTwoItems($waiter);

        $this->assertFalse($kitchen->hasPermission('orders.split'));

        $response = $this->actingAs($kitchen)->get(route('admin.orders.bill-split.create', $order));

        $response->assertForbidden();
    }
}
