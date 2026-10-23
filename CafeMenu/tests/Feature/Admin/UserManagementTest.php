<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_owner_can_view_users_list(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)->get(route('admin.users.index'))->assertOk();
    }

    public function test_manager_can_view_users_list(): void
    {
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)->get(route('admin.users.index'))->assertOk();
    }

    public function test_waiter_cannot_view_users_list(): void
    {
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($waiter)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_waiter_cannot_directly_access_edit_page_of_another_user_idor(): void
    {
        $waiter = $this->userWithRole('waiter');
        $target = $this->userWithRole('cashier');

        $this->actingAs($waiter)
            ->get(route('admin.users.edit', $target))
            ->assertForbidden();
    }

    public function test_owner_can_create_a_staff_user(): void
    {
        $owner = $this->userWithRole('owner');
        $cashierRole = Role::where('name', 'cashier')->first();

        $response = $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'New Cashier',
            'email' => 'cashier@example.com',
            'password' => 'Password!23',
            'password_confirmation' => 'Password!23',
            'role_id' => $cashierRole->id,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'cashier@example.com',
            'role_id' => $cashierRole->id,
        ]);
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $owner = $this->userWithRole('owner');

        $this->actingAs($owner)
            ->patch(route('admin.users.toggle-active', $owner))
            ->assertForbidden();

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_cannot_deactivate_the_last_active_owner(): void
    {
        $owner = $this->userWithRole('owner');
        $manager = $this->userWithRole('manager');

        $this->actingAs($manager)
            ->patch(route('admin.users.toggle-active', $owner))
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($owner->fresh()->is_active);
    }

    public function test_manager_can_deactivate_a_non_owner_staff_member(): void
    {
        $manager = $this->userWithRole('manager');
        $waiter = $this->userWithRole('waiter');

        $this->actingAs($manager)->patch(route('admin.users.toggle-active', $waiter));

        $this->assertFalse($waiter->fresh()->is_active);
    }
}
