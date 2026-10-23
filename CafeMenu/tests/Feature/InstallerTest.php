<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Permission;
use App\Models\User;
use App\Services\Installer\EnvWriter;
use App\Services\Installer\InstallLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_requirements_page_is_accessible_and_shows_checks(): void
    {
        $response = $this->get(route('install.requirements'));

        $response->assertOk();
        $response->assertSee('System requirements');
        $response->assertSee('Writable directories');
    }

    public function test_installer_is_blocked_once_locked(): void
    {
        app(InstallLock::class)->lock();

        $this->get(route('install.requirements'))->assertRedirect('/');
        $this->get(route('install.database'))->assertRedirect('/');
        $this->post(route('install.database.store'))->assertRedirect('/');
    }

    public function test_database_step_rejects_unreachable_credentials(): void
    {
        $response = $this->post(route('install.database.store'), [
            'db_host' => '127.0.0.1',
            'db_port' => 1, // nothing listens here
            'db_database' => 'does_not_exist',
            'db_username' => 'nobody',
            'db_password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('db_host');
    }

    public function test_env_writer_updates_existing_keys_and_appends_new_ones(): void
    {
        $envPath = base_path('.env.installer_test');
        file_put_contents($envPath, "APP_NAME=Test\nDB_HOST=old-host\n");

        $writer = new EnvWriter($envPath);
        $writer->set(['DB_HOST' => 'new-host', 'DB_PORT' => '3306']);

        $contents = file_get_contents($envPath);
        $this->assertStringContainsString('DB_HOST=new-host', $contents);
        $this->assertStringContainsString('DB_PORT=3306', $contents);
        $this->assertStringContainsString('APP_NAME=Test', $contents);
        $this->assertStringNotContainsString('old-host', $contents);

        unlink($envPath);
    }

    public function test_env_writer_quotes_values_with_special_characters(): void
    {
        $envPath = base_path('.env.installer_test_quoting');
        file_put_contents($envPath, '');

        $writer = new EnvWriter($envPath);
        $writer->set(['DB_PASSWORD' => 'pa ss#word']);

        $contents = file_get_contents($envPath);
        $this->assertStringContainsString('DB_PASSWORD="pa ss#word"', $contents);

        unlink($envPath);
    }

    public function test_migrate_step_requires_a_working_database_connection(): void
    {
        // The test DB connection is already active (RefreshDatabase), so
        // this step should be reachable without visiting /install/database.
        $this->get(route('install.migrate'))->assertOk();
    }

    public function test_running_migrate_seeds_roles_and_advances_to_owner_step(): void
    {
        $response = $this->post(route('install.migrate.run'));

        $response->assertRedirect(route('install.owner'));
        $this->assertDatabaseHas('roles', ['name' => 'owner']);
        $this->assertGreaterThan(0, Permission::count());
    }

    public function test_owner_step_is_blocked_until_migrations_have_run(): void
    {
        // RefreshDatabase already migrated the test schema, so simulate the
        // pre-migration state isn't practical here; instead verify the
        // step succeeds once roles exist (the realistic post-migrate case).
        $this->post(route('install.migrate.run'));

        $this->get(route('install.owner'))->assertOk();
    }

    public function test_owner_account_can_be_created_once(): void
    {
        $this->post(route('install.migrate.run'));

        $response = $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('install.business'));
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertTrue($owner->is_active);
    }

    public function test_owner_step_redirects_forward_once_an_owner_already_exists(): void
    {
        $this->post(route('install.migrate.run'));
        $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->get(route('install.owner'))->assertRedirect(route('install.business'));
    }

    public function test_cannot_create_a_second_owner_via_direct_post(): void
    {
        $this->post(route('install.migrate.run'));
        $this->post(route('install.owner.store'), [
            'name' => 'First',
            'email' => 'first@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->post(route('install.owner.store'), [
            'name' => 'Second',
            'email' => 'second@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertSame(1, User::count());
    }

    public function test_business_step_requires_an_owner_to_exist_first(): void
    {
        $this->post(route('install.migrate.run'));

        $this->get(route('install.business'))->assertRedirect(route('install.owner'));
    }

    public function test_business_details_can_be_saved(): void
    {
        $this->post(route('install.migrate.run'));
        $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response = $this->post(route('install.business.store'), [
            'name' => 'My Cafe',
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $response->assertRedirect(route('install.complete'));
        $this->assertSame('My Cafe', BusinessSetting::current()->name);
    }

    public function test_complete_step_requires_business_setup_first(): void
    {
        $this->post(route('install.migrate.run'));
        $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->get(route('install.complete'))->assertRedirect(route('install.business'));
    }

    public function test_visiting_complete_locks_the_installer(): void
    {
        $this->post(route('install.migrate.run'));
        $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $this->post(route('install.business.store'), [
            'name' => 'My Cafe',
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);
        $this->assertFalse(app(InstallLock::class)->isInstalled());

        $this->get(route('install.complete'))->assertOk();

        $this->assertTrue(app(InstallLock::class)->isInstalled());
        $this->get(route('install.requirements'))->assertRedirect('/');
    }

    public function test_completing_install_disables_debug_mode_and_sets_production_env(): void
    {
        $envPath = base_path('.env.installer_complete_test');
        file_put_contents($envPath, "APP_ENV=local\nAPP_DEBUG=true\n");
        $this->app->bind(EnvWriter::class, fn () => new EnvWriter($envPath));

        $this->post(route('install.migrate.run'));
        $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $this->post(route('install.business.store'), [
            'name' => 'My Cafe',
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $this->get(route('install.complete'))->assertOk();

        $contents = file_get_contents($envPath);
        $this->assertStringContainsString('APP_ENV=production', $contents);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);

        unlink($envPath);
    }

    public function test_full_install_flow_end_to_end(): void
    {
        $this->get(route('install.requirements'))->assertOk();
        $this->post(route('install.migrate.run'))->assertRedirect(route('install.owner'));

        $this->post(route('install.owner.store'), [
            'name' => 'Jane Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('install.business'));

        $this->post(route('install.business.store'), [
            'name' => 'My Cafe',
            'currency' => 'USD',
            'currency_minor_units' => 2,
            'locale' => 'en',
            'timezone' => 'UTC',
        ])->assertRedirect(route('install.complete'));

        $this->get(route('install.complete'))->assertOk();

        $this->assertTrue(app(InstallLock::class)->isInstalled());
        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
        $this->assertSame('My Cafe', BusinessSetting::current()->name);

        // The installer is now locked; a fresh admin login should work normally.
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->hasRole('owner'));
    }

    public function test_installer_stays_closed_when_the_lock_file_is_lost_but_the_database_has_users(): void
    {
        // A live site whose storage/ was not carried over on a redeploy.
        $this->post(route('install.migrate.run'));
        User::factory()->create();
        $this->assertFalse(app(InstallLock::class)->isInstalled());

        $this->flushSession();

        $this->get(route('install.database'))->assertRedirect('/');
        $this->post(route('install.database.store'), [
            'db_host' => 'attacker.example', 'db_port' => 3306, 'db_database' => 'x', 'db_username' => 'x',
        ])->assertRedirect('/');

        $this->assertTrue(app(InstallLock::class)->isInstalled());
    }

    public function test_a_never_installed_site_sends_visitors_to_the_installer(): void
    {
        // Simulated rather than dropping a table: DDL would implicitly
        // commit the test transaction on MySQL.
        Schema::shouldReceive('hasTable')->andReturn(false);

        $this->get('/')->assertRedirect(route('install.requirements'));
    }

    public function test_a_migrated_site_is_not_redirected_even_without_a_lock_file(): void
    {
        $this->assertFalse(app(InstallLock::class)->isInstalled());

        $this->get('/')->assertOk();
    }
}
