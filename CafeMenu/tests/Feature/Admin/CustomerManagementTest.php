<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
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

    public function test_owner_can_view_customers_list(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = Customer::create(['name' => 'Jane Doe', 'phone' => '555-0001']);

        $this->actingAs($owner)->get(route('admin.customers.index'))->assertOk()->assertSee($customer->name);
        $this->actingAs($owner)->get(route('admin.customers.create'))->assertOk();
        $this->actingAs($owner)->get(route('admin.customers.edit', $customer))->assertOk();
    }

    public function test_kitchen_cannot_view_customers_list(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.customers.index'))->assertForbidden();
    }

    public function test_kitchen_cannot_directly_access_customer_edit_page_idor(): void
    {
        $kitchen = $this->userWithRole('kitchen');
        $customer = Customer::create(['name' => 'Jane Doe']);

        $this->actingAs($kitchen)->get(route('admin.customers.edit', $customer))->assertForbidden();
    }

    public function test_owner_can_create_a_customer(): void
    {
        $owner = $this->userWithRole('owner');
        $group = CustomerGroup::create(['name' => 'VIP']);

        $response = $this->actingAs($owner)->post(route('admin.customers.store'), [
            'name' => 'John Smith',
            'phone' => '555-0002',
            'email' => 'john@example.com',
            'customer_group_id' => $group->id,
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseHas('customers', [
            'name' => 'John Smith',
            'phone' => '555-0002',
            'status' => 'active',
            'customer_group_id' => $group->id,
        ]);
    }

    public function test_customer_phone_must_be_unique(): void
    {
        $owner = $this->userWithRole('owner');
        Customer::create(['name' => 'Existing', 'phone' => '555-0003']);

        $response = $this->actingAs($owner)->post(route('admin.customers.store'), [
            'name' => 'New Customer',
            'phone' => '555-0003',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_updating_a_customer_does_not_trip_the_phone_uniqueness_check_on_itself(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = Customer::create(['name' => 'Jane Doe', 'phone' => '555-0004']);

        $response = $this->actingAs($owner)->put(route('admin.customers.update', $customer), [
            'name' => 'Jane Doe Updated',
            'phone' => '555-0004',
        ]);

        $response->assertRedirect(route('admin.customers.index'));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Jane Doe Updated']);
    }

    public function test_owner_can_toggle_customer_status(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = Customer::create(['name' => 'Jane Doe', 'status' => 'active']);

        $this->actingAs($owner)->patch(route('admin.customers.toggle-status', $customer));
        $this->assertSame('blocked', $customer->fresh()->status);

        $this->actingAs($owner)->patch(route('admin.customers.toggle-status', $customer));
        $this->assertSame('active', $customer->fresh()->status);
    }

    public function test_customers_have_no_destroy_route(): void
    {
        $this->assertFalse(Route::has('admin.customers.destroy'));
    }

    public function test_customer_search_filters_by_name_or_phone(): void
    {
        $owner = $this->userWithRole('owner');
        Customer::create(['name' => 'Alice Johnson', 'phone' => '555-1111']);
        Customer::create(['name' => 'Bob Baker', 'phone' => '555-2222']);

        $response = $this->actingAs($owner)->get(route('admin.customers.index', ['search' => 'Alice']));

        $response->assertOk()->assertSee('Alice Johnson')->assertDontSee('Bob Baker');
    }

    public function test_customer_edit_page_shows_spending_stats_and_recent_orders(): void
    {
        $owner = $this->userWithRole('owner');
        $customer = Customer::create(['name' => 'Big Spender']);

        Order::create([
            'type' => 'takeaway',
            'customer_id' => $customer->id,
            'subtotal' => 10000,
            'grand_total' => 10000,
            'currency' => 'USD',
            'status' => 'completed',
        ]);
        Order::create([
            'type' => 'takeaway',
            'customer_id' => $customer->id,
            'subtotal' => 5000,
            'grand_total' => 5000,
            'currency' => 'USD',
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($owner)->get(route('admin.customers.edit', $customer));

        $response->assertOk();
        // Two orders total, but only the completed one counts toward sales stats.
        $response->assertSee(\App\Support\Money::format(10000));
    }

    public function test_owner_can_manage_customer_groups(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.customer-groups.index'))->assertOk();
        $this->actingAs($owner)->get(route('admin.customer-groups.create'))->assertOk();

        $response = $this->actingAs($owner)->post(route('admin.customer-groups.store'), [
            'name' => 'Regulars',
            'description' => 'Frequent visitors',
        ]);

        $response->assertRedirect(route('admin.customer-groups.index'));
        $this->assertDatabaseHas('customer_groups', ['name' => 'Regulars']);

        $group = CustomerGroup::where('name', 'Regulars')->firstOrFail();
        $this->actingAs($owner)->get(route('admin.customer-groups.edit', $group))->assertOk();

        $updateResponse = $this->actingAs($owner)->put(route('admin.customer-groups.update', $group), [
            'name' => 'VIP Regulars',
        ]);
        $updateResponse->assertRedirect(route('admin.customer-groups.index'));
        $this->assertDatabaseHas('customer_groups', ['id' => $group->id, 'name' => 'VIP Regulars']);
    }

    public function test_kitchen_cannot_manage_customer_groups(): void
    {
        $kitchen = $this->userWithRole('kitchen');

        $this->actingAs($kitchen)->get(route('admin.customer-groups.index'))->assertForbidden();
    }

    public function test_cannot_delete_customer_group_with_customers(): void
    {
        $owner = $this->userWithRole('owner');
        $group = CustomerGroup::create(['name' => 'VIP']);
        Customer::create(['name' => 'Jane Doe', 'customer_group_id' => $group->id]);

        $response = $this->actingAs($owner)->delete(route('admin.customer-groups.destroy', $group));

        $response->assertRedirect(route('admin.customer-groups.index'));
        $this->assertModelExists($group);
    }

    public function test_can_delete_empty_customer_group(): void
    {
        $owner = $this->userWithRole('owner');
        $group = CustomerGroup::create(['name' => 'Empty Group']);

        $this->actingAs($owner)->delete(route('admin.customer-groups.destroy', $group));

        $this->assertModelMissing($group);
    }
}
