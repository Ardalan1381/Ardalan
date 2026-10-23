<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGatewayVisibilityTest extends TestCase
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

    private function validSettingsPayload(array $overrides = []): array
    {
        $workingHours = collect(range(0, 6))->map(fn ($day) => [
            'day' => $day,
            'is_closed' => true,
        ])->all();

        return array_merge([
            'name' => 'My Cafe',
            'currency' => 'IRT',
            'currency_minor_units' => 0,
            'locale' => 'en',
            'timezone' => 'UTC',
            'working_hours' => $workingHours,
        ], $overrides);
    }

    public function test_settings_page_shows_no_gateway_checkboxes_when_none_configured(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->get(route('admin.settings.business.edit'));

        $response->assertOk()->assertDontSee('enabled_online_gateways');
    }

    public function test_owner_can_enable_a_configured_gateway(): void
    {
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validSettingsPayload([
            'enabled_online_gateways' => ['zarinpal'],
        ]));

        $this->assertSame(['zarinpal'], BusinessSetting::current()->enabled_online_gateways);
    }

    public function test_unchecking_all_gateways_clears_the_setting(): void
    {
        config(['payment.gateways.zarinpal.merchant_id' => 'test-merchant']);
        BusinessSetting::current()->update(['enabled_online_gateways' => ['zarinpal']]);
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validSettingsPayload());

        $this->assertSame([], BusinessSetting::current()->enabled_online_gateways);
    }

    public function test_unknown_gateway_key_is_rejected(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validSettingsPayload([
            'enabled_online_gateways' => ['not-a-real-gateway'],
        ]));

        $response->assertSessionHasErrors('enabled_online_gateways.0');
    }

    public function test_admin_payments_list_shows_gateway_name(): void
    {
        $owner = $this->userWithRole('owner');
        $product = Product::factory()->create(['base_price' => 10000]);

        $this->postJson(route('checkout.store'), [
            'type' => 'takeaway',
            'customer_name' => 'Jane',
            'customer_phone' => '555-1234',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $order = Order::latest('id')->firstOrFail();
        $payment = $order->payments()->create(['method' => 'online', 'amount' => $order->grand_total, 'status' => 'paid']);
        $payment->gateway = 'zarinpal';
        $payment->save();

        $this->actingAs($owner)->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee(__('payments.gateways.zarinpal'));
    }
}
