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
        $this->call(RolePermissionSeeder::class);

        User::factory()->create([
            'name' => 'Test Owner',
            'email' => 'test@example.com',
            'role_id' => Role::where('name', 'owner')->first()->id,
        ]);

        $this->call(DemoDataSeeder::class);
    }
}
