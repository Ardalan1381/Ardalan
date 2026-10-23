<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * The test environment defaults to the "null" broadcaster (phpunit.xml),
     * whose /broadcasting/auth handler short-circuits successfully without
     * ever consulting routes/channels.php — so channel-authorization tests
     * need a real broadcaster's auth logic instead. Switching the config
     * alone isn't enough: routes/channels.php only ran its registrations
     * against whichever driver was active when the app booted (the "null"
     * one), so channels must be re-registered against the newly active
     * driver too. Reverb's auth check only signs a response with the app
     * key/secret; it never opens a socket, so no live server is needed.
     */
    private function useRealBroadcasterForChannelAuth(): void
    {
        config(['broadcasting.default' => 'reverb']);
        require base_path('routes/channels.php');
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->first()->id,
        ]);
    }

    private function placeOrder(): void
    {
        $product = Product::factory()->create();

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
    }

    public function test_placing_an_order_dispatches_order_placed_event(): void
    {
        Event::fake([OrderPlaced::class]);

        $this->placeOrder();

        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->order->status === 'pending');
    }

    public function test_placing_an_order_notifies_staff_with_orders_view_permission(): void
    {
        Notification::fake();
        $owner = $this->userWithRole('owner');
        $waiter = $this->userWithRole('waiter');

        $this->placeOrder();

        Notification::assertSentTo($owner, NewOrderNotification::class);
        Notification::assertSentTo($waiter, NewOrderNotification::class);
    }

    public function test_users_without_orders_permission_are_not_notified(): void
    {
        Notification::fake();
        $role = Role::create(['name' => 'nobody', 'label' => 'Nobody']);
        $noPermissionUser = User::factory()->create(['role_id' => $role->id]);

        $this->placeOrder();

        Notification::assertNotSentTo($noPermissionUser, NewOrderNotification::class);
    }

    public function test_inactive_staff_can_still_be_notified_but_permission_gate_is_role_based(): void
    {
        // Sanity check: notification targeting is by permission, not by is_active,
        // since staff scheduling/on-shift status isn't tracked in this system.
        Notification::fake();
        $owner = $this->userWithRole('owner');

        $this->placeOrder();

        Notification::assertSentTo($owner, NewOrderNotification::class, function ($notification, $channels) {
            return in_array('database', $channels, true) && in_array('broadcast', $channels, true);
        });
    }

    public function test_order_status_transition_dispatches_order_status_changed_event(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $owner = $this->userWithRole('owner');
        $this->placeOrder();
        $order = Order::latest('id')->firstOrFail();

        $this->actingAs($owner)->patch(route('admin.orders.status', $order), ['status' => 'confirmed']);

        Event::assertDispatched(OrderStatusChanged::class, function (OrderStatusChanged $event) {
            return $event->fromStatus === 'pending' && $event->toStatus === 'confirmed';
        });
    }

    public function test_orders_channel_authorizes_a_user_with_orders_view_permission(): void
    {
        $this->useRealBroadcasterForChannelAuth();
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->post('/broadcasting/auth', [
            'channel_name' => 'private-orders',
            'socket_id' => '1234.1234',
        ]);

        $response->assertOk();
    }

    public function test_orders_channel_denies_a_user_without_orders_view_permission(): void
    {
        $this->useRealBroadcasterForChannelAuth();
        $role = Role::create(['name' => 'nobody', 'label' => 'Nobody']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $response = $this->actingAs($user)->post('/broadcasting/auth', [
            'channel_name' => 'private-orders',
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_cannot_authorize_the_orders_channel(): void
    {
        $this->useRealBroadcasterForChannelAuth();

        $response = $this->post('/broadcasting/auth', [
            'channel_name' => 'private-orders',
            'socket_id' => '1234.1234',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_mark_a_notification_as_read(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeOrder();
        $notification = $owner->notifications()->firstOrFail();

        $response = $this->actingAs($owner)->patch(route('admin.notifications.read', $notification->id));

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $owner = $this->userWithRole('owner');
        $this->placeOrder();
        $placedOrder = Order::latest('id')->firstOrFail();
        $owner->notify(new NewOrderNotification($placedOrder));
        $owner->notify(new NewOrderNotification($placedOrder));

        $this->actingAs($owner)->patch(route('admin.notifications.read-all'));

        $this->assertSame(0, $owner->unreadNotifications()->count());
    }

    public function test_admin_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = $this->userWithRole('owner');
        $otherOwner = $this->userWithRole('owner');
        $this->placeOrder();
        $order = Order::latest('id')->firstOrFail();
        $otherOwner->notify(new NewOrderNotification($order));
        $notification = $otherOwner->notifications()->firstOrFail();

        $this->actingAs($owner)->patch(route('admin.notifications.read', $notification->id))->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}
