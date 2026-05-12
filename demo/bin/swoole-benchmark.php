<?php

declare(strict_types=1);

use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;
use Swoole\Coroutine;

require dirname(__DIR__) . '/autoload.php';

if (! extension_loaded('swoole')) {
    echo "ext-swoole is not loaded\n";
    exit(1);
}

$iterations = (int) ($argv[1] ?? 3);

echo "BEAR.Async Swoole Benchmark\n";
echo "===========================\n";
echo "8 embedded SQL resources\n";
echo "Expected: Sync ~sequential, Swoole ~parallel execution\n\n";

// Sync execution (prod-hal-app context - no async module)
echo "Sync execution (prod-hal-app)...\n";
$syncApp = Injector::getInstance('prod-hal-app')->getInstance(AppInterface::class);
$syncResource = $syncApp->resource;

$syncTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $start = hrtime(true);
    $response = $syncResource->get->uri('app://self/dashboard')->eager->request();
    $view = (string) $response;
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    $syncTimes[] = $elapsed;
    printf("  Run %d: %.2f ms\n", $i + 1, $elapsed);
}

$syncAvg = array_sum($syncTimes) / count($syncTimes);
printf("  Average: %.2f ms\n\n", $syncAvg);

Coroutine\run(static function () use ($iterations, $syncAvg): void {
    // Swoole execution (prod-swoole-hal-app context)
    echo "Swoole execution (prod-swoole-hal-app)...\n";
    $swooleApp = Injector::getInstance('prod-swoole-hal-app')->getInstance(AppInterface::class);
    $swooleResource = $swooleApp->resource;

    $swooleTimes = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $response = $swooleResource->get->uri('app://self/dashboard')->eager->request();
        $view = (string) $response;
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        $swooleTimes[] = $elapsed;
        printf("  Run %d: %.2f ms\n", $i + 1, $elapsed);
    }

    $swooleAvg = array_sum($swooleTimes) / count($swooleTimes);
    printf("  Average: %.2f ms\n\n", $swooleAvg);

    // Results
    echo "Results\n";
    echo "-------\n";
    printf("Sync average:   %.2f ms\n", $syncAvg);
    printf("Swoole average: %.2f ms\n", $swooleAvg);

    if ($swooleAvg > 0) {
        $speedup = $syncAvg / $swooleAvg;
        printf("Speedup:        %.2fx\n", $speedup);
    }

    // Verify HAL output contains embedded resources
    $data = json_decode($view, true);
    $embedCount = isset($data['_embedded']) ? count($data['_embedded']) : 0;
    printf("\nVerification: %d embedded resources in HAL output\n", $embedCount);
});
