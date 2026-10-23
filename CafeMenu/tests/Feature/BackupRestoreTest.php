<?php

namespace Tests\Feature;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * No RefreshDatabase here — these tests intentionally flip
 * database.default mid-test to simulate a mysql-configured install, which
 * conflicts with RefreshDatabase's transactional wrapper around the
 * sqlite test connection. None of these tests touch Eloquent/the DB.
 */
class BackupRestoreTest extends TestCase
{
    public function test_backup_is_rejected_on_a_non_mysql_connection(): void
    {
        // The test environment's default connection is sqlite.
        $this->artisan('app:backup')->assertExitCode(1);
    }

    public function test_restore_is_rejected_on_a_non_mysql_connection(): void
    {
        $fixture = storage_path('app/backups/does-not-matter.tar.gz');
        File::ensureDirectoryExists(dirname($fixture));
        File::put($fixture, 'not a real archive');

        $this->artisan('app:restore', ['archive' => $fixture, '--force' => true])->assertExitCode(1);

        File::delete($fixture);
    }

    public function test_restore_fails_fast_when_the_archive_file_does_not_exist(): void
    {
        $this->artisan('app:restore', ['archive' => storage_path('app/backups/nope.tar.gz'), '--force' => true])
            ->assertExitCode(1);
    }

    public function test_restore_without_force_can_be_cancelled_at_the_confirmation_prompt(): void
    {
        config(['database.default' => 'mysql']);
        $fixture = storage_path('app/backups/fixture.tar.gz');
        File::ensureDirectoryExists(dirname($fixture));
        File::put($fixture, 'not a real archive');

        $this->artisan('app:restore', ['archive' => $fixture])
            ->expectsConfirmation('This will REPLACE the current database and public media storage with the contents of this backup. Continue?', 'no')
            ->assertExitCode(0);

        File::delete($fixture);
    }

    public function test_backup_invokes_mysqldump_with_the_configured_connection_and_never_leaks_the_password_as_a_plain_argument(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => '3306',
            'database.connections.mysql.username' => 'cafe',
            'database.connections.mysql.password' => 'super-secret',
            'database.connections.mysql.database' => 'cafe',
        ]);

        Process::fake();

        $this->artisan('app:backup')->assertExitCode(0);

        Process::assertRan(function (PendingProcess $process) {
            $command = $process->command;

            if (! is_array($command) || $command[0] !== 'mysqldump') {
                return false;
            }

            $this->assertContains('cafe', $command); // username and database both happen to be "cafe" here
            $this->assertContains('-h', $command);
            $this->assertContains('127.0.0.1', $command);
            $this->assertNotContains('super-secret', $command, 'The password must never appear as a plain process argument.');
            $this->assertSame('super-secret', $process->environment['MYSQL_PWD'] ?? null);

            return true;
        });

        Process::assertRan(fn (PendingProcess $process) => is_array($process->command) && $process->command[0] === 'tar' && $process->command[1] === '-czf');
    }

    public function test_backup_warns_that_env_is_not_included_and_app_key_must_be_kept_separately(): void
    {
        config(['database.default' => 'mysql']);
        Process::fake();

        $this->artisan('app:backup')->expectsOutputToContain('APP_KEY')->assertExitCode(0);
    }

    public function test_full_restore_happy_path_extracts_and_restores_via_mysql(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'cafe',
        ]);

        $fixture = storage_path('app/backups/fixture.tar.gz');
        File::ensureDirectoryExists(dirname($fixture));
        File::put($fixture, 'not a real archive — tar itself is faked below');

        Process::fake(function (PendingProcess $process) {
            $command = $process->command;

            if (is_array($command) && $command[0] === 'tar' && $command[1] === '-xzf') {
                // Simulate a real extraction: the destination directory is
                // whatever followed '-C' — write the expected files there
                // so the command's own post-extraction checks pass.
                $destinationIndex = array_search('-C', $command, true) + 1;
                $destination = $command[$destinationIndex];
                File::ensureDirectoryExists($destination);
                File::put("{$destination}/database.sql", "-- fake dump\n");

                return Process::result(output: 'extracted');
            }

            return Process::result(output: 'ok');
        });

        $this->artisan('app:restore', ['archive' => $fixture, '--force' => true])
            ->assertExitCode(0);

        Process::assertRan(fn (PendingProcess $process) => is_array($process->command) && $process->command[0] === 'mysql' && in_array('cafe', $process->command, true));

        File::delete($fixture);
    }

    public function test_restore_fails_when_the_archive_does_not_contain_a_database_dump(): void
    {
        config(['database.default' => 'mysql']);
        $fixture = storage_path('app/backups/fixture-empty.tar.gz');
        File::ensureDirectoryExists(dirname($fixture));
        File::put($fixture, 'not a real archive');

        // tar "succeeds" but extracts nothing containing database.sql.
        Process::fake();

        $this->artisan('app:restore', ['archive' => $fixture, '--force' => true])
            ->assertExitCode(1);

        File::delete($fixture);
    }
}
