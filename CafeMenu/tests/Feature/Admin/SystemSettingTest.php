<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
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

    public function test_owner_can_view_and_update_system_settings(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.settings.system.edit'))->assertOk();

        $response = $this->actingAs($owner)->put(route('admin.settings.system.update'), [
            'pagination_per_page' => 10,
        ]);

        $response->assertRedirect(route('admin.settings.system.edit'));
        $this->assertSame(10, SystemSetting::get('pagination_per_page'));
    }

    public function test_pagination_per_page_must_be_within_bounds(): void
    {
        $owner = $this->userWithRole('owner');

        $response = $this->actingAs($owner)->put(route('admin.settings.system.update'), [
            'pagination_per_page' => 1000,
        ]);

        $response->assertSessionHasErrors('pagination_per_page');
    }

    public function test_waiter_cannot_view_system_settings(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.settings.system.edit'))->assertForbidden();
    }

    public function test_system_setting_get_returns_default_when_missing(): void
    {
        $this->assertSame(20, SystemSetting::get('pagination_per_page', 20));
    }

    public function test_system_setting_set_and_get_roundtrip(): void
    {
        SystemSetting::set('pagination_per_page', 42);

        $this->assertSame(42, SystemSetting::get('pagination_per_page'));
    }

    public function test_pagination_setting_affects_the_products_listing(): void
    {
        SystemSetting::set('pagination_per_page', 2);
        $owner = $this->userWithRole('owner');
        Product::factory()->count(3)->create();

        $response = $this->actingAs($owner)->get(route('admin.products.index'));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('products'));
    }
}
