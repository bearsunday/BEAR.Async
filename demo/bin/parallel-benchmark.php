<?php

declare(strict_types=1);

use BEAR\Async\Module\ParallelRuntimeModule;
use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;

require dirname(__DIR__) . '/autoload.php';

echo "BEAR.Async Parallel Benchmark\n";
echo "==============================\n";
echo "8 embedded SQL resources, per-request cost (server steady state)\n";
echo "Boot-time costs (DI compile, Runtime spawn) are excluded — they\n";
echo "happen once at server start, not per request.\n\n";

echo "Sync (prod-hal-app)...\n";
$syncResource = Injector::getInstance('prod-hal-app')
    ->getInstance(AppInterface::class)
    ->resource;

$start = hrtime(true);
$response = $syncResource->get->uri('app://self/dashboard?user_id=1')->eager->request();
$view = (string) $response;
$syncTime = (hrtime(true) - $start) / 1_000_000;
printf("  Elapsed: %.2f ms\n\n", $syncTime);

echo "Parallel (prod-hal-app + ParallelRuntimeModule override)...\n";
$parallelResource = Injector::getOverrideInstance(
    'prod-hal-app',
    new ParallelRuntimeModule('prod-hal-app', 8),
)->getInstance(AppInterface::class)->resource;

// Warmup with a different user_id so the timed run's embed URIs don't hit the
// PendingRequests cache that the interceptor seeded during this warmup.
// The pool of parallel\Runtime workers is reused across both requests; only
// the per-URI result cache differs.
$response = $parallelResource->get->uri('app://self/dashboard?user_id=999')->eager->request();
(string) $response;

$start = hrtime(true);
$response = $parallelResource->get->uri('app://self/dashboard?user_id=1')->eager->request();
$view = (string) $response;
$parallelTime = (hrtime(true) - $start) / 1_000_000;
printf("  Elapsed: %.2f ms\n\n", $parallelTime);

echo "Results\n";
echo "-------\n";
printf("Sync:     %.2f ms\n", $syncTime);
printf("Parallel: %.2f ms\n", $parallelTime);
printf("Speedup:  %.2fx\n", $syncTime / $parallelTime);

$data = json_decode($view, true);
$embedCount = isset($data['_embedded']) ? count($data['_embedded']) : 0;
$expectedEmbeds = 8;
printf("\nVerification: %d embedded resources in HAL output (expected %d)\n", $embedCount, $expectedEmbeds);

if ($embedCount !== $expectedEmbeds) {
    printf("\nFAILURE: Expected %d embedded resources, got %d\n", $expectedEmbeds, $embedCount);
    exit(1);
}

printf("\nSUCCESS: %d embedded resources resolved through parallel runtime\n", $embedCount);
exit(0);
