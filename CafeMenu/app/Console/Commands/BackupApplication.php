<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A minimal, dependency-free backup: a mysqldump of the database plus a
 * copy of the public media storage, archived together as one .tar.gz —
 * shelling out to the standard `mysqldump`/`tar` binaries already expected
 * on any self-hosted Linux deployment of this app, rather than adding a
 * backup package.
 */
class BackupApplication extends Command
{
    protected $signature = 'app:backup {--path= : Directory to write the backup into (defaults to storage/app/backups)}';

    protected $description = 'Creates a full backup (database + public media storage) as one .tar.gz archive';

    public function handle(): int
    {
        $connectionName = config('database.default');
        $config = config("database.connections.{$connectionName}");

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            $this->error("app:backup only supports mysql/mariadb connections (current default connection driver: \"{$config['driver']}\").");

            return self::FAILURE;
        }

        $timestamp = now()->format('Y-m-d_His');
        $backupDir = $this->option('path') ?: storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $workDir = storage_path("app/backups/tmp_{$timestamp}");
        File::ensureDirectoryExists($workDir);

        try {
            $this->info('Dumping database...');
            $this->dumpDatabase($config, "{$workDir}/database.sql");

            $publicStoragePath = storage_path('app/public');
            if (File::isDirectory($publicStoragePath)) {
                $this->info('Copying media storage...');
                File::copyDirectory($publicStoragePath, "{$workDir}/storage");
            }

            $archivePath = "{$backupDir}/cafe-backup-{$timestamp}.tar.gz";
            $this->info('Creating archive...');
            $this->createArchive($workDir, $archivePath);

            $this->info("Backup created: {$archivePath}");
            $this->warn('This archive does NOT include your .env file. Keep a separate, secure copy of APP_KEY — without it, encrypted columns (e.g. payment gateway credentials) cannot be read after a restore.');

            return self::SUCCESS;
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpDatabase(array $config, string $outputPath): void
    {
        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => $config['password'] ?? ''])
            ->run([
                'mysqldump',
                '-h', $config['host'],
                '-P', (string) $config['port'],
                '-u', $config['username'],
                '--single-transaction',
                '--routines',
                '--result-file='.$outputPath,
                $config['database'],
            ]);

        if ($result->failed()) {
            throw new \RuntimeException('mysqldump failed: '.$result->errorOutput());
        }
    }

    private function createArchive(string $sourceDir, string $archivePath): void
    {
        $result = Process::timeout(600)->run(['tar', '-czf', $archivePath, '-C', $sourceDir, '.']);

        if ($result->failed()) {
            throw new \RuntimeException('Creating the backup archive failed: '.$result->errorOutput());
        }
    }
}
