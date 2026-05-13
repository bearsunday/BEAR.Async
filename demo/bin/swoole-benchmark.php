<?php

declare(strict_types=1);

use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;

require dirname(__DIR__) . '/autoload.php';

if (! extension_loaded('swoole')) {
    echo "ext-swoole is not loaded\n";
    exit(1);
}

if (isXdebugActive()) {
    fwrite(STDERR, "Xdebug is enabled. Swoole coroutines are not safe with active Xdebug; disable Xdebug or set XDEBUG_MODE=off.\n");
    exit(1);
}

echo "BEAR.Async Swoole Benchmark\n";
echo "===========================\n";
echo "8 embedded SQL resources, cold one-shot reference\n";
echo "This includes DI lookup and coroutine scheduler setup for this CLI run.\n";
echo "Use composer steady-state-swoole for HTTP steady-state measurements.\n\n";

echo "Sync execution (prod-hal-app)...\n";
$syncResource = Injector::getInstance('prod-hal-app')
    ->getInstance(AppInterface::class)
    ->resource;

$start = hrtime(true);
$response = $syncResource->get->uri('app://self/dashboard')->eager->request();
$view = (string) $response;
$syncTime = (hrtime(true) - $start) / 1_000_000;
printf("  Elapsed: %.2f ms\n\n", $syncTime);

Coroutine\run(static function () use ($syncTime): void {
    echo "Swoole execution (prod-swoole-hal-api-app)...\n";
    $injector = Injector::getInstance('prod-swoole-hal-api-app');
    $injector->getInstance(PDOPool::class)->fill();
    $swooleResource = $injector->getInstance(AppInterface::class)->resource;

    $start = hrtime(true);
    $response = $swooleResource->get->uri('app://self/dashboard')->eager->request();
    $view = (string) $response;
    $swooleTime = (hrtime(true) - $start) / 1_000_000;
    printf("  Elapsed: %.2f ms\n\n", $swooleTime);

    echo "Results\n";
    echo "-------\n";
    printf("Sync:   %.2f ms\n", $syncTime);
    printf("Swoole: %.2f ms\n", $swooleTime);

    if ($swooleTime > 0) {
        printf("Ratio:  %.2fx\n", $syncTime / $swooleTime);
    }

    echo "\nNote: this one-shot CLI run is a cold-start reference, not a\n";
    echo "      steady-state per-request benchmark.\n";

    $data = json_decode($view, true);
    $embedCount = isset($data['_embedded']) ? count($data['_embedded']) : 0;
    printf("\nVerification: %d embedded resources in HAL output\n", $embedCount);
});

function isXdebugActive(): bool
{
    if (! extension_loaded('xdebug')) {
        return false;
    }

    $mode = getenv('XDEBUG_MODE');
    if ($mode !== false) {
        return $mode !== '' && $mode !== 'off';
    }

    $iniMode = ini_get('xdebug.mode');
    if ($iniMode === false) {
        return true;
    }

    return $iniMode !== '' && $iniMode !== 'off';
}
