<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Creates well-known demo logins (password "password"); never on a
        // live installation. Production needs only RolePermissionSeeder,
        // which the installer runs.
        if (app()->isProduction()) {
            throw new \RuntimeException(class_basename(static::class).' creates demo accounts with known passwords and must not run in production. Seed Database\\Seeders\\RolePermissionSeeder instead.');
        }

        $this->call(RolePermissionSeeder::class);

        User::factory()->create([
            'name' => 'Test Owner',
            'email' => 'test@example.com',
            'role_id' => Role::where('name', 'owner')->first()->id,
        ]);

        $this->call(DemoDataSeeder::class);
    }
}
