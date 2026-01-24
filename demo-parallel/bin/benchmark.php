<?php

declare(strict_types=1);

use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;

require dirname(__DIR__) . '/autoload.php';

$iterations = (int) ($argv[1] ?? 3);

echo "BEAR.Async Parallel Benchmark\n";
echo "==============================\n";
echo "10 embedded resources × 50ms delay each\n";
echo "Expected: Sync ~500ms, Parallel ~150ms (with 4 workers)\n\n";

// Sync execution (prod-hal-app context - no parallel module)
echo "Sync execution (prod-hal-app)...\n";
$syncApp = Injector::getInstance('prod-hal-app')->getInstance(AppInterface::class);
$syncResource = $syncApp->resource;

$syncTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $start = hrtime(true);
    $response = $syncResource->get->uri('app://self/dashboard')->eager->request();
    // Force embed resolution via rendering
    $view = (string) $response;
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    $syncTimes[] = $elapsed;
    printf("  Run %d: %.2f ms\n", $i + 1, $elapsed);
}

$syncAvg = array_sum($syncTimes) / count($syncTimes);
printf("  Average: %.2f ms\n\n", $syncAvg);

// Parallel execution (prod-parallel-hal-app context)
echo "Parallel execution (prod-parallel-hal-app)...\n";
$parallelApp = Injector::getInstance('prod-parallel-hal-app')->getInstance(AppInterface::class);
$parallelResource = $parallelApp->resource;

$parallelTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $start = hrtime(true);
    $response = $parallelResource->get->uri('app://self/dashboard')->eager->request();
    // Force embed resolution via rendering
    $view = (string) $response;
    $elapsed = (hrtime(true) - $start) / 1_000_000;
    $parallelTimes[] = $elapsed;
    printf("  Run %d: %.2f ms\n", $i + 1, $elapsed);
}

$parallelAvg = array_sum($parallelTimes) / count($parallelTimes);
printf("  Average: %.2f ms\n\n", $parallelAvg);

// Results
echo "Results\n";
echo "-------\n";
printf("Sync average:     %.2f ms\n", $syncAvg);
printf("Parallel average: %.2f ms\n", $parallelAvg);
printf("Speedup:          %.2fx\n", $syncAvg / $parallelAvg);

// Verify HAL output contains embedded resources
$data = json_decode($view, true);
$embedCount = isset($data['_embedded']) ? count($data['_embedded']) : 0;
printf("\nVerification: %d embedded resources in HAL output\n", $embedCount);

// Exit code based on speedup
$speedup = $syncAvg / $parallelAvg;
if ($speedup < 2.0) {
    echo "\nWARNING: Speedup is less than 2x - parallel execution may not be working correctly\n";
    exit(1);
}

echo "\nSUCCESS: Parallel execution achieved {$speedup}x speedup\n";
exit(0);
