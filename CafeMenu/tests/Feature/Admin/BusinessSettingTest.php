<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BusinessSettingTest extends TestCase
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

    private function validPayload(array $overrides = []): array
    {
        $workingHours = collect(range(0, 6))->map(fn ($day) => [
            'day' => $day,
            'is_closed' => $day === 0,
            'opens_at' => $day === 0 ? null : '09:00',
            'closes_at' => $day === 0 ? null : '22:00',
        ])->all();

        return array_merge([
            'name' => 'My Cafe',
            'contact_email' => 'owner@example.com',
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'locale' => 'en',
            'timezone' => 'UTC',
            'tax_enabled' => '1',
            'tax_rate_bps' => 500,
            'service_charge_enabled' => '0',
            'theme_color_primary' => '#112233',
            'theme_color_secondary' => '#445566',
            'accepts_dine_in' => '1',
            'accepts_takeaway' => '1',
            'working_hours' => $workingHours,
        ], $overrides);
    }

    public function test_owner_can_view_business_settings_page(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.settings.business.edit'))->assertOk();
    }

    /**
     * This page has the highest density of <x-checkbox ...> usage in the
     * app (tax/service-charge/order-type/gateway toggles) — a broad guard
     * against the whole class of "@checked() on a component tag silently
     * fails to compile" bug (see ComponentRenderingTest for the isolated
     * case) rather than asserting every individual field.
     */
    public function test_business_settings_page_renders_real_checkbox_inputs_not_raw_component_tags(): void
    {
        $owner = $this->userWithRole('owner');

        $html = $this->actingAs($owner)->get(route('admin.settings.business.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<x-checkbox', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="tax_enabled"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<input[^>]+name="accepts_dine_in"[^>]*>/', $html);
    }

    public function test_waiter_cannot_view_business_settings(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.settings.business.edit'))->assertForbidden();
    }

    public function test_owner_can_toggle_cash_on_pickup(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'accepts_cash_on_pickup' => '1',
        ]));
        $this->assertTrue(BusinessSetting::current()->accepts_cash_on_pickup);

        $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload());
        $this->assertFalse(BusinessSetting::current()->accepts_cash_on_pickup);
    }

    public function test_owner_can_update_business_settings(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload());

        $response->assertRedirect(route('admin.settings.business.edit'));
        $settings = BusinessSetting::current();
        $this->assertSame('My Cafe', $settings->name);
        $this->assertSame('USD', $settings->currency);
        $this->assertTrue($settings->tax_enabled);
        $this->assertCount(7, $settings->working_hours);
    }

    public function test_owner_can_configure_a_display_currency_for_another_locale(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'locale' => 'fa',
            'display_currencies' => [
                'en' => ['is_active' => '1', 'code' => 'USD', 'symbol' => '$', 'minor_units' => '2'],
            ],
        ]));

        $response->assertRedirect(route('admin.settings.business.edit'));
        $settings = BusinessSetting::current()->fresh();
        $this->assertSame('fa', $settings->locale);
        $this->assertTrue($settings->display_currencies['en']['is_active']);
        $this->assertSame('USD', $settings->display_currencies['en']['code']);
        $this->assertSame('$', $settings->display_currencies['en']['symbol']);
        $this->assertSame(2, $settings->display_currencies['en']['minor_units']);
    }

    public function test_unchecking_a_display_currency_disables_it_without_losing_its_data(): void
    {
        $owner = $this->userWithRole('owner');
        BusinessSetting::current()->update([
            'display_currencies' => ['en' => ['is_active' => true, 'code' => 'USD', 'symbol' => '$', 'minor_units' => 2]],
        ]);

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'display_currencies' => [
                'en' => ['code' => 'USD', 'symbol' => '$', 'minor_units' => '2'],
            ],
        ]));

        $response->assertRedirect(route('admin.settings.business.edit'));
        $settings = BusinessSetting::current()->fresh();
        $this->assertFalse($settings->display_currencies['en']['is_active']);
        $this->assertSame('USD', $settings->display_currencies['en']['code']);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'timezone' => 'Not/ARealZone',
        ]));

        $response->assertSessionHasErrors('timezone');
    }

    public function test_invalid_theme_color_is_rejected(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'theme_color_primary' => 'not-a-color',
        ]));

        $response->assertSessionHasErrors('theme_color_primary');
    }

    public function test_working_hours_requires_all_seven_days(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'working_hours' => [['day' => 0, 'is_closed' => true]],
        ]));

        $response->assertSessionHasErrors('working_hours');
    }

    public function test_closes_at_must_be_after_opens_at(): void
    {
        $owner = $this->userWithRole('owner');
        $workingHours = collect(range(0, 6))->map(fn ($day) => [
            'day' => $day,
            'is_closed' => false,
            'opens_at' => '22:00',
            'closes_at' => '09:00',
        ])->all();

        $response = $this->actingAs($owner)->put(route('admin.settings.business.update'), $this->validPayload([
            'working_hours' => $workingHours,
        ]));

        $response->assertSessionHasErrors('working_hours.0.closes_at');
    }

    public function test_owner_can_upload_and_replace_the_logo(): void
    {
        Storage::fake('public');
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post(route('admin.settings.business.logo.store'), [
            'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $settings = BusinessSetting::current();
        $this->assertCount(1, $settings->media);
        $firstPath = $settings->media->first()->path;

        $this->actingAs($owner)->post(route('admin.settings.business.logo.store'), [
            'file' => UploadedFile::fake()->image('logo2.png', 200, 200),
        ]);

        $settings->refresh();
        $this->assertCount(1, $settings->media);
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_owner_can_remove_the_logo(): void
    {
        Storage::fake('public');
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->post(route('admin.settings.business.logo.store'), [
            'file' => UploadedFile::fake()->image('logo.png'),
        ]);

        $this->actingAs($owner)->delete(route('admin.settings.business.logo.destroy'));

        $this->assertCount(0, BusinessSetting::current()->media);
    }
}
