<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Restores a backup created by app:backup. DESTRUCTIVE — overwrites the
 * current database and public media storage — so it always confirms
 * unless --force is passed explicitly.
 */
class RestoreApplication extends Command
{
    protected $signature = 'app:restore {archive : Path to a .tar.gz backup created by app:backup} {--force : Skip the confirmation prompt}';

    protected $description = 'Restores a database + storage backup created by app:backup (destructive)';

    public function handle(): int
    {
        $archivePath = $this->argument('archive');

        if (! File::exists($archivePath)) {
            $this->error("Backup file not found: {$archivePath}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will REPLACE the current database and public media storage with the contents of this backup. Continue?', false)) {
            $this->warn('Restore cancelled.');

            return self::SUCCESS;
        }

        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error("app:restore only supports mysql/mariadb connections (current default connection driver: \"{$config['driver']}\").");

            return self::FAILURE;
        }

        $workDir = storage_path('app/backups/restore_tmp_'.now()->format('Y-m-d_His'));
        File::ensureDirectoryExists($workDir);

        try {
            $this->info('Extracting archive...');
            $this->extractArchive($archivePath, $workDir);

            $sqlPath = "{$workDir}/database.sql";

            if (! File::exists($sqlPath)) {
                $this->error('database.sql not found inside the archive — is this a valid app:backup file?');

                return self::FAILURE;
            }

            $this->info('Restoring database...');
            $this->restoreDatabase($config, $sqlPath);

            $storageBackupPath = "{$workDir}/storage";

            if (File::isDirectory($storageBackupPath)) {
                $this->info('Restoring media storage...');
                File::deleteDirectory(storage_path('app/public'));
                File::copyDirectory($storageBackupPath, storage_path('app/public'));
            }

            $this->info('Restore complete.');

            return self::SUCCESS;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    private function extractArchive(string $archivePath, string $destination): void
    {
        $result = Process::timeout(600)->run(['tar', '-xzf', $archivePath, '-C', $destination]);

        if ($result->failed()) {
            throw new \RuntimeException('Extracting the backup archive failed: '.$result->errorOutput());
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function restoreDatabase(array $config, string $sqlPath): void
    {
        $handle = fopen($sqlPath, 'r');

        try {
            $result = Process::timeout(600)
                ->env(['MYSQL_PWD' => $config['password'] ?? ''])
                ->input($handle)
                ->run([
                    'mysql',
                    '-h', $config['host'],
                    '-P', (string) $config['port'],
                    '-u', $config['username'],
                    $config['database'],
                ]);

            if ($result->failed()) {
                throw new \RuntimeException('Restoring the database failed: '.$result->errorOutput());
            }
        } finally {
            fclose($handle);
        }
    }
}
