<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Base class for tests that prove an operation is safe under real
 * concurrency: N separate PHP processes, each with its own MySQL/MariaDB
 * connection, released at the same instant against committed data.
 *
 * SQLite serialises all writers on a file lock (and :memory: databases are
 * per-connection), so it cannot exhibit — or disprove — these races; the
 * tests skip themselves unless the default connection is mysql/mariadb.
 * See docs/engineering/TESTING.md for how to run them.
 *
 * These tests commit real rows, so the schema is rebuilt before and after
 * each test to leave nothing behind for the transactional (RefreshDatabase)
 * tests that may run later in the same process.
 */
abstract class ConcurrencyTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Concurrency tests need a MySQL/MariaDB connection (see docs/engineering/TESTING.md).');
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Artisan::call('migrate:fresh', ['--force' => true]);
        }

        parent::tearDown();
    }

    /**
     * Runs $workers copies of the scenario simultaneously and returns each
     * worker's decoded JSON outcome.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $args  One args array for all workers, or one per worker.
     * @return list<array{ok: bool, result?: mixed, error?: string, class?: string}>
     */
    protected function race(string $scenario, int $workers, array $args = []): array
    {
        $perWorker = array_is_list($args) && $args !== [] ? $args : array_fill(0, $workers, $args);
        $startAt = microtime(true) + 1.5;

        $processes = [];
        foreach ($perWorker as $workerArgs) {
            $process = new Process(
                [PHP_BINARY, base_path('tests/Concurrency/worker.php'), $scenario, (string) $startAt, json_encode($workerArgs)],
                base_path(),
                $this->workerEnvironment(),
                timeout: 60,
            );
            $process->start();
            $processes[] = $process;
        }

        $outcomes = [];
        foreach ($processes as $process) {
            $process->wait();
            $lines = array_values(array_filter(explode(PHP_EOL, trim($process->getOutput()))));
            $decoded = json_decode((string) end($lines), true);

            $this->assertIsArray($decoded, "Worker produced no result. stdout: {$process->getOutput()} stderr: {$process->getErrorOutput()}");
            $outcomes[] = $decoded;
        }

        return $outcomes;
    }

    /**
     * @param  list<array<string, mixed>>  $outcomes
     */
    protected function successes(array $outcomes): int
    {
        return count(array_filter($outcomes, fn (array $o) => $o['ok']));
    }

    /**
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'DB_CONNECTION' => $connection,
            'DB_HOST' => (string) $config['host'],
            'DB_PORT' => (string) $config['port'],
            'DB_DATABASE' => (string) $config['database'],
            'DB_USERNAME' => (string) $config['username'],
            'DB_PASSWORD' => (string) $config['password'],
            'BROADCAST_CONNECTION' => 'null',
            'QUEUE_CONNECTION' => 'sync',
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'SESSION_DRIVER' => 'array',
        ];
    }
}
