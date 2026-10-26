<?php

use App\Models\Sandbox;
use App\Services\BookingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$basePath = dirname(__DIR__);

require $basePath.'/vendor/autoload.php';

$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (($argv[1] ?? null) === 'worker') {
    $payload = json_decode($argv[2], true, flags: JSON_THROW_ON_ERROR);
    $barrier = $argv[3];
    $ready = $argv[4];

    file_put_contents($ready, 'ready');
    while (! file_exists($barrier)) {
        usleep(1000);
    }

    try {
        DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
        app(BookingService::class)->create($payload);
        fwrite(STDOUT, "created\n");
    } catch (ValidationException) {
        fwrite(STDOUT, "rejected\n");
    } catch (QueryException $exception) {
        fwrite(STDERR, $exception->getMessage()."\n");
        exit(1);
    }

    exit(0);
}

if (DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "This smoke test requires MySQL or MariaDB; SQLite does not provide the row-locking behavior being tested.\n");
    exit(1);
}

$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sandbox-booking-race-'.bin2hex(random_bytes(8));
mkdir($directory);

$barrier = $directory.DIRECTORY_SEPARATOR.'go';
$readyFiles = [
    $directory.DIRECTORY_SEPARATOR.'worker-1.ready',
    $directory.DIRECTORY_SEPARATOR.'worker-2.ready',
];
$processes = [];
$sandbox = null;

try {
    $sandbox = Sandbox::create([
        'name' => 'concurrency-smoke-'.bin2hex(random_bytes(6)),
        'os_family' => 'windows',
    ]);

    $startsAt = now('UTC')->addDay()->startOfDay()->setTime(10, 0);
    $payload = json_encode([
        'sandbox_id' => $sandbox->id,
        'analyst_name' => 'Concurrency smoke test',
        'sample_reference' => 'concurrency-smoke-test',
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $startsAt->addHour()->toIso8601String(),
    ], JSON_THROW_ON_ERROR);

    foreach ($readyFiles as $readyFile) {
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, __FILE__, 'worker', $payload, $barrier, $readyFile],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $basePath,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start a concurrency worker process.');
        }

        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }

    $readyDeadline = microtime(true) + 15;
    while (count(array_filter($readyFiles, 'file_exists')) !== 2 && microtime(true) < $readyDeadline) {
        usleep(10000);
    }

    if (count(array_filter($readyFiles, 'file_exists')) !== 2) {
        throw new RuntimeException('Concurrency workers did not become ready within 15 seconds.');
    }

    file_put_contents($barrier, 'go');

    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException(trim($stderr) ?: 'A concurrency worker failed.');
        }

        $results[] = trim($stdout);
    }

    sort($results);
    if ($results !== ['created', 'rejected']) {
        throw new RuntimeException('Expected one booking to succeed and one to be rejected; got: '.implode(', ', $results));
    }

    fwrite(STDOUT, "PASS: simultaneous requests produced one booking and one rejection.\n");
} finally {
    foreach ($processes as [$process, $pipes]) {
        if (is_resource($process)) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    if ($sandbox instanceof Sandbox) {
        $sandbox->delete();
    }

    foreach (array_merge([$barrier], $readyFiles) as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
    if (is_dir($directory)) {
        rmdir($directory);
    }
}
