<?php

/*
 * One concurrency-test worker: a separate PHP process with its own database
 * connection, booted exactly like an artisan command. It sleeps until the
 * shared start time (so all workers hit the database together), runs one
 * named scenario from Tests\Concurrency\Scenarios, and prints a single JSON
 * line: {"ok": true, "result": ...} or {"ok": false, "error": "...", "class": "..."}.
 *
 * Usage: php tests/Concurrency/worker.php <scenario> <start-microtime> <json-args>
 */

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[$script, $scenario, $startAt, $json] = $argv + [null, null, '0', '{}'];
$args = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

// Open the connection before the barrier so connection setup time does not
// stagger the workers.
Illuminate\Support\Facades\DB::connection()->getPdo();

$wait = (float) $startAt - microtime(true);
if ($wait > 0) {
    usleep((int) ($wait * 1_000_000));
}

try {
    $result = Tests\Concurrency\Scenarios::run($scenario, $args);
    echo json_encode(['ok' => true, 'result' => $result]), PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'class' => $e::class]), PHP_EOL;
}
