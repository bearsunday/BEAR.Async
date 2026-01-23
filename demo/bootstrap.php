<?php

declare(strict_types=1);

use BEAR\Async\Adapter;
use BEAR\Async\Module\AsyncModule;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use Ray\Di\Injector;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Create resource client with specified adapter
 *
 * @param Adapter $adapter Async adapter to use
 */
function createResourceClient(Adapter $adapter): ResourceInterface
{
    $module = new ResourceModule('BEAR\Async\Demo');
    $module->override(new AsyncModule($adapter));

    return (new Injector($module, __DIR__ . '/tmp'))->getInstance(ResourceInterface::class);
}

/**
 * Async-aware delay function
 *
 * Uses the appropriate delay mechanism for the current execution context:
 * - Swoole coroutine context: Swoole\Coroutine::sleep()
 * - Amp Fiber context: Amp\delay()
 * - Default: usleep()
 *
 * @param int $milliseconds Delay duration in milliseconds
 *
 * @psalm-suppress UndefinedClass
 * @psalm-suppress UndefinedFunction
 */
function asyncDelay(int $milliseconds): void
{
    $seconds = $milliseconds / 1000;

    // Check Swoole coroutine context
    if (extension_loaded('swoole') && class_exists('Swoole\Coroutine') && Swoole\Coroutine::getCid() > 0) {
        Swoole\Coroutine::sleep($seconds);

        return;
    }

    // Check Amp Fiber context
    if (function_exists('Amp\delay') && Fiber::getCurrent() !== null) {
        call_user_func('Amp\delay', $seconds);

        return;
    }

    // Default: blocking sleep
    usleep($milliseconds * 1000);
}
