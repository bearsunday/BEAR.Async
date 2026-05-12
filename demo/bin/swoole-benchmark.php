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

echo "BEAR.Async Swoole Benchmark\n";
echo "===========================\n";
echo "8 embedded SQL resources, per-request cost (server steady state)\n";
echo "Boot-time costs (DI compile, coroutine scheduler init) are excluded.\n\n";

echo "Sync (prod-hal-app)...\n";
$syncResource = Injector::getInstance('prod-hal-app')
    ->getInstance(AppInterface::class)
    ->resource;

$start = hrtime(true);
$response = $syncResource->get->uri('app://self/dashboard?user_id=1')->eager->request();
$view = (string) $response;
$syncTime = (hrtime(true) - $start) / 1_000_000;
printf("  Elapsed: %.2f ms\n\n", $syncTime);

Coroutine\run(static function () use ($syncTime): void {
    echo "Swoole (prod-swoole-hal-app)...\n";
    $swooleInjector = Injector::getInstance('prod-swoole-hal-app');
    $swooleResource = $swooleInjector->getInstance(AppInterface::class)->resource;

    // Warmup with a different user_id so the timed run's embed URIs don't hit
    // the PendingRequests cache that the interceptor seeded during this warmup.
    // The coroutine scheduler and PDO pool are reused across both requests;
    // only the per-URI result cache differs.
    $response = $swooleResource->get->uri('app://self/dashboard?user_id=999')->eager->request();
    (string) $response;

    $start = hrtime(true);
    $response = $swooleResource->get->uri('app://self/dashboard?user_id=1')->eager->request();
    $view = (string) $response;
    $swooleTime = (hrtime(true) - $start) / 1_000_000;
    printf("  Elapsed: %.2f ms\n\n", $swooleTime);

    echo "Results\n";
    echo "-------\n";
    printf("Sync:   %.2f ms\n", $syncTime);
    printf("Swoole: %.2f ms\n", $swooleTime);

    if ($swooleTime > 0) {
        printf("Speedup: %.2fx\n", $syncTime / $swooleTime);
    }

    $data = json_decode($view, true);
    $embedCount = isset($data['_embedded']) ? count($data['_embedded']) : 0;
    printf("\nVerification: %d embedded resources in HAL output\n", $embedCount);
});
