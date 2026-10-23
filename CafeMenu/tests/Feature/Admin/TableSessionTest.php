<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Table;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableSessionTest extends TestCase
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

    private function placeDineInOrder(Table $table): Order
    {
        $product = Product::factory()->create();
        $this->get('/t/'.$table->qr_token);

        $this->postJson(route('checkout.store'), [
            'type' => 'dine_in',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_dine_in_checkout_opens_a_table_session(): void
    {
        $table = Table::factory()->create();

        $order = $this->placeDineInOrder($table);

        $this->assertNotNull($order->table_session_id);
        $this->assertSame('open', $order->tableSession->status);
    }

    public function test_second_dine_in_order_reuses_the_open_session(): void
    {
        $table = Table::factory()->create();

        $first = $this->placeDineInOrder($table);
        $second = $this->placeDineInOrder($table);

        $this->assertSame($first->table_session_id, $second->table_session_id);
        $this->assertSame(1, TableSession::where('table_id', $table->id)->count());
    }

    public function test_owner_can_close_an_open_session(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $this->placeDineInOrder($table);
        $session = TableSession::where('table_id', $table->id)->firstOrFail();

        $this->actingAs($owner)->patch(route('admin.table-sessions.close', $session))
            ->assertRedirect(route('admin.tables.session', $table));

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertSame($owner->id, $session->closed_by_user_id);
    }

    public function test_cannot_close_an_already_closed_session(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $this->placeDineInOrder($table);
        $session = TableSession::where('table_id', $table->id)->firstOrFail();

        $this->actingAs($owner)->patch(route('admin.table-sessions.close', $session));
        $response = $this->actingAs($owner)->patch(route('admin.table-sessions.close', $session));

        $response->assertRedirect();
        $response->assertSessionHasErrors('session');
    }

    public function test_after_a_dine_in_order_a_new_visit_gets_a_new_session_once_closed(): void
    {
        $table = Table::factory()->create();
        $owner = $this->userWithRole('owner');

        $first = $this->placeDineInOrder($table);
        $this->actingAs($owner)->patch(route('admin.table-sessions.close', $first->tableSession));

        $second = $this->placeDineInOrder($table);

        $this->assertNotSame($first->table_session_id, $second->table_session_id);
    }

    public function test_owner_can_transfer_an_open_session_to_another_table(): void
    {
        $owner = $this->userWithRole('owner');
        $tableA = Table::factory()->create();
        $tableB = Table::factory()->create();
        $order = $this->placeDineInOrder($tableA);
        $session = $order->tableSession;

        $response = $this->actingAs($owner)->patch(route('admin.table-sessions.transfer', $session), [
            'table_id' => $tableB->id,
        ]);

        $response->assertRedirect(route('admin.tables.session', $tableB));
        $this->assertSame($tableB->id, $session->fresh()->table_id);
    }

    public function test_transferring_a_session_also_updates_its_orders_table_id(): void
    {
        $owner = $this->userWithRole('owner');
        $tableA = Table::factory()->create();
        $tableB = Table::factory()->create();
        $order = $this->placeDineInOrder($tableA);

        $this->actingAs($owner)->patch(route('admin.table-sessions.transfer', $order->tableSession), [
            'table_id' => $tableB->id,
        ]);

        $order->refresh();
        $this->assertSame($tableB->id, $order->table_id);
        $this->assertSame($tableB->id, $order->tableSession->table_id);
    }

    public function test_cannot_transfer_to_a_table_that_already_has_an_open_session(): void
    {
        $owner = $this->userWithRole('owner');
        $tableA = Table::factory()->create();
        $tableB = Table::factory()->create();
        $orderA = $this->placeDineInOrder($tableA);
        $this->placeDineInOrder($tableB);

        $response = $this->actingAs($owner)->patch(route('admin.table-sessions.transfer', $orderA->tableSession), [
            'table_id' => $tableB->id,
        ]);

        $response->assertSessionHasErrors('table_id');
        $this->assertSame($tableA->id, $orderA->tableSession->fresh()->table_id);
    }

    public function test_owner_can_merge_two_open_sessions(): void
    {
        $owner = $this->userWithRole('owner');
        $tableA = Table::factory()->create();
        $tableB = Table::factory()->create();
        $orderA = $this->placeDineInOrder($tableA);
        $orderB = $this->placeDineInOrder($tableB);

        $response = $this->actingAs($owner)->patch(route('admin.table-sessions.merge', $orderA->tableSession), [
            'destination_session_id' => $orderB->table_session_id,
        ]);

        $response->assertRedirect(route('admin.tables.session', $tableB));

        $this->assertSame('closed', $orderA->tableSession->fresh()->status);
        $this->assertSame($orderB->table_session_id, $orderA->tableSession->fresh()->merged_into_session_id);
        $this->assertSame($orderB->table_session_id, $orderA->fresh()->table_session_id);
    }

    public function test_merging_a_session_also_updates_its_orders_table_id_to_the_destination_table(): void
    {
        $owner = $this->userWithRole('owner');
        $tableA = Table::factory()->create();
        $tableB = Table::factory()->create();
        $orderA = $this->placeDineInOrder($tableA);
        $orderB = $this->placeDineInOrder($tableB);

        $this->actingAs($owner)->patch(route('admin.table-sessions.merge', $orderA->tableSession), [
            'destination_session_id' => $orderB->table_session_id,
        ]);

        $orderA->refresh();
        $this->assertSame($tableB->id, $orderA->table_id);
        $this->assertSame($tableB->id, $orderA->tableSession->table_id);
    }

    public function test_cannot_merge_a_session_into_itself(): void
    {
        $owner = $this->userWithRole('owner');
        $table = Table::factory()->create();
        $order = $this->placeDineInOrder($table);

        $response = $this->actingAs($owner)->patch(route('admin.table-sessions.merge', $order->tableSession), [
            'destination_session_id' => $order->table_session_id,
        ]);

        $response->assertSessionHasErrors('destination');
    }

    public function test_waiter_without_tables_manage_permission_cannot_manage_sessions(): void
    {
        $waiter = $this->userWithRole('waiter');
        $table = Table::factory()->create();
        $order = $this->placeDineInOrder($table);

        $this->actingAs($waiter)->get(route('admin.tables.session', $table))->assertForbidden();
        $this->actingAs($waiter)->patch(route('admin.table-sessions.close', $order->tableSession))->assertForbidden();
    }
}
