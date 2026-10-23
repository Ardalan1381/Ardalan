<?php

namespace Tests\Feature\Payments;

use App\Models\BusinessSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\Support\GatewayCredentials;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GatewayCredentialsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function owner(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', 'owner')->first()->id,
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

    public function test_saving_credentials_through_admin_ui_makes_the_gateway_configured(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validSettingsPayload([
            'gateway_credentials' => [
                'zarinpal' => ['merchant_id' => 'abc-merchant-id', 'sandbox' => '1'],
            ],
        ]))->assertRedirect(route('admin.settings.business.edit'));

        $this->assertSame('abc-merchant-id', BusinessSetting::current()->gatewayCredential('zarinpal', 'merchant_id'));
        $this->assertTrue(BusinessSetting::current()->gatewayCredential('zarinpal', 'sandbox'));
        $this->assertContains('zarinpal', app(PaymentGatewayManager::class)->configured());
    }

    public function test_blank_credential_field_keeps_the_previously_saved_value(): void
    {
        $owner = $this->owner();
        BusinessSetting::current()->mergeGatewayCredentials([
            'zarinpal' => ['merchant_id' => 'original-merchant-id', 'sandbox' => false],
        ]);

        $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validSettingsPayload([
            'gateway_credentials' => [
                'zarinpal' => ['merchant_id' => '', 'sandbox' => '1'],
            ],
        ]));

        $settings = BusinessSetting::current();
        $this->assertSame('original-merchant-id', $settings->gatewayCredential('zarinpal', 'merchant_id'));
        $this->assertTrue($settings->gatewayCredential('zarinpal', 'sandbox'));
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        BusinessSetting::current()->mergeGatewayCredentials([
            'mellat' => ['terminal_id' => '12345', 'username' => 'shop-user', 'password' => 'super-secret'],
        ]);

        $raw = DB::table('business_settings')->where('id', 1)->value('payment_gateway_credentials');

        $this->assertStringNotContainsString('super-secret', $raw);
        $this->assertStringNotContainsString('shop-user', $raw);
    }

    public function test_database_credential_takes_priority_over_env_config(): void
    {
        config(['payment.gateways.zibal.merchant' => 'env-merchant']);
        BusinessSetting::current()->mergeGatewayCredentials(['zibal' => ['merchant' => 'db-merchant']]);

        $this->assertSame('db-merchant', GatewayCredentials::get('zibal', 'merchant'));
    }

    public function test_env_config_is_used_when_no_database_credential_is_set(): void
    {
        config(['payment.gateways.zibal.merchant' => 'env-merchant']);

        $this->assertSame('env-merchant', GatewayCredentials::get('zibal', 'merchant'));
    }

    public function test_settings_page_never_echoes_a_saved_secret_back(): void
    {
        $owner = $this->owner();
        BusinessSetting::current()->mergeGatewayCredentials([
            'mellat' => ['terminal_id' => '12345', 'username' => 'shop-user', 'password' => 'super-secret-password'],
        ]);

        $this->actingAs($owner)->get(route('admin.settings.business.edit'))
            ->assertOk()
            ->assertDontSee('super-secret-password')
            ->assertDontSee('shop-user');
    }
}
