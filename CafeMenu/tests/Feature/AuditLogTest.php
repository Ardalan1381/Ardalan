<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
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

    public function test_successful_login_is_audited(): void
    {
        $owner = $this->userWithRole('owner');

        $this->post('/login', ['email' => $owner->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'auth.login_succeeded',
        ]);
    }

    public function test_failed_login_is_audited(): void
    {
        $owner = $this->userWithRole('owner');

        $this->post('/login', ['email' => $owner->email, 'password' => 'wrong-password']);

        $log = AuditLog::where('action', 'auth.login_failed')->firstOrFail();
        $this->assertNull($log->user_id);
        $this->assertSame($owner->email, $log->new_values['email']);
    }

    public function test_inactive_account_login_attempt_is_audited(): void
    {
        $owner = $this->userWithRole('owner');
        $owner->update(['is_active' => false]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'auth.login_blocked_inactive',
        ]);
    }

    public function test_logout_is_audited(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post('/logout');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_user_creation_is_audited(): void
    {
        $owner = $this->userWithRole('owner');
        $cashierRole = Role::where('name', 'cashier')->first();

        $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'New Cashier',
            'email' => 'newcashier@example.com',
            'password' => 'Password!23',
            'password_confirmation' => 'Password!23',
            'role_id' => $cashierRole->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'user.created',
        ]);
    }

    public function test_role_change_is_audited_as_a_distinct_event(): void
    {
        $owner = $this->userWithRole('owner');
        $target = $this->userWithRole('waiter');
        $managerRole = Role::where('name', 'manager')->first();

        $this->actingAs($owner)->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'role_id' => $managerRole->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.updated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.role_changed']);
    }

    public function test_user_status_toggle_is_audited(): void
    {
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($owner)->patch(route('admin.users.toggle-active', $waiter));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'user.status_toggled',
        ]);
    }

    public function test_product_lifecycle_is_audited(): void
    {
        $owner = $this->userWithRole('owner');
        $category = Category::factory()->create();

        $this->actingAs($owner)->post(route('admin.products.store'), [
            'category_id' => $category->id,
            'name' => 'Latte',
            'base_price' => 5000,
        ]);
        $product = Product::where('name', 'Latte')->firstOrFail();

        $this->actingAs($owner)->put(route('admin.products.update', $product), [
            'category_id' => $category->id,
            'name' => 'Latte Deluxe',
            'base_price' => 6000,
        ]);

        $this->actingAs($owner)->delete(route('admin.products.destroy', $product));

        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'auditable_id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.updated', 'auditable_id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.deleted', 'auditable_id' => $product->id]);
    }

    private function placeOrder(): Order
    {
        $product = Product::factory()->create(['base_price' => 5000]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        return Order::latest('id')->firstOrFail();
    }

    public function test_order_status_change_is_audited(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'confirmed']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'order.status_changed',
            'auditable_id' => $order->id,
        ]);
    }

    public function test_order_cancellation_is_audited_with_its_own_action_name(): void
    {
        $owner = $this->userWithRole('owner');
        $order = $this->placeOrder();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'cancelled']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order.cancelled',
            'auditable_id' => $order->id,
        ]);
    }

    public function test_payment_recorded_is_audited(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder();

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'cash', 'amount' => 5000, 'status' => 'paid',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $cashier->id,
            'action' => 'payment.recorded',
        ]);
    }

    public function test_payment_status_change_is_audited(): void
    {
        $cashier = $this->userWithRole('cashier');
        $order = $this->placeOrder();

        $this->actingAs($cashier)->post(route('admin.orders.payments.store', $order), [
            'method' => 'pay_later', 'amount' => 5000, 'status' => 'pending',
        ]);
        $payment = Payment::firstOrFail();

        $this->actingAs($cashier)->patch(route('admin.payments.status', $payment), ['status' => 'paid']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payment.status_changed',
            'auditable_id' => $payment->id,
        ]);
    }

    public function test_business_settings_update_is_audited(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->put(route('admin.settings.business.update'), [
            'name' => 'Updated Cafe Name',
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'settings.business_updated',
            'auditable_id' => BusinessSetting::current()->id,
        ]);
    }

    public function test_system_settings_update_is_audited(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->put(route('admin.settings.system.update'), [
            'pagination_per_page' => 15,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $owner->id,
            'action' => 'settings.system_updated',
        ]);
    }

    public function test_owner_can_view_audit_logs(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_manager_can_view_audit_logs(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_waiter_cannot_view_audit_logs(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.audit-logs.index'))->assertForbidden();
    }
}
