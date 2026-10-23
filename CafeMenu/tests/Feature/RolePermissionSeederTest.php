<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_role_receives_every_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $owner = Role::where('name', 'owner')->first();

        $this->assertSame(Permission::count(), $owner->permissions()->count());
        $this->assertGreaterThan(0, Permission::count());
    }

    public function test_waiter_role_does_not_receive_user_management_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $waiter = Role::where('name', 'waiter')->first();

        $this->assertFalse($waiter->permissions->contains('name', 'users.manage'));
    }
}
