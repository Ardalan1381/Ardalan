<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeders_refuse_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        foreach ([DatabaseSeeder::class, DemoDataSeeder::class] as $seeder) {
            try {
                $this->app->make($seeder)->setContainer($this->app)->run();
                $this->fail("{$seeder} ran in production.");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('must not run in production', $e->getMessage());
            }
        }

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
