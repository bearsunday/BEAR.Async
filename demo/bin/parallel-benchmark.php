<?php

declare(strict_types=1);

use BEAR\Async\Module\ParallelRuntimeModule;
use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;

require dirname(__DIR__) . '/autoload.php';

echo "BEAR.Async Parallel Benchmark\n";
echo "==============================\n";
echo "8 embedded SQL resources, cold one-shot reference\n";
echo "This includes DI lookup and one-time ext-parallel Runtime spawn cost.\n";
echo "Use composer steady-state-parallel for HTTP steady-state measurements.\n\n";

echo "Sync execution (prod-hal-app)...\n";
$syncResource = Injector::getInstance('prod-hal-app')
    ->getInstance(AppInterface::class)
    ->resource;

$start = hrtime(true);
$response = $syncResource->get->uri('app://self/dashboard')->eager->request();
$view = (string) $response;
$syncTime = (hrtime(true) - $start) / 1_000_000;
printf("  Elapsed: %.2f ms\n\n", $syncTime);

echo "Parallel execution (prod-hal-app + ParallelRuntimeModule override)...\n";
$parallelResource = Injector::getOverrideInstance(
    'prod-hal-app',
    new ParallelRuntimeModule('prod-hal-app', 8),
)->getInstance(AppInterface::class)->resource;

$start = hrtime(true);
$response = $parallelResource->get->uri('app://self/dashboard')->eager->request();
$view = (string) $response;
$parallelTime = (hrtime(true) - $start) / 1_000_000;
printf("  Elapsed: %.2f ms (includes one-time thread pool spawn cost)\n\n", $parallelTime);

echo "Results\n";
echo "-------\n";
printf("Sync:     %.2f ms\n", $syncTime);
printf("Parallel: %.2f ms\n", $parallelTime);
printf("Ratio:    %.2fx\n", $syncTime / $parallelTime);
echo "\nNote: this one-shot CLI run includes ext-parallel Runtime spawn (~hundreds of ms).\n";
echo "      It is a cold-start reference, not a steady-state per-request benchmark.\n";

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
