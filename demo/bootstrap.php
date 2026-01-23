<?php

declare(strict_types=1);

use BEAR\Async\Module\AsyncSwooleModule;
use BEAR\Resource\Module\ResourceModule;
use BEAR\Resource\ResourceInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Create resource client with specified module
 */
function createResourceClient(AbstractModule $asyncModule): ResourceInterface
{
    $module = new ResourceModule('BEAR\Async\Demo');
    $module->override($asyncModule);

    return (new Injector($module, __DIR__ . '/tmp'))->getInstance(ResourceInterface::class);
}

/**
 * Async-aware delay function
 *
 * Uses the appropriate delay mechanism for the current execution context:
 * - Swoole coroutine context: Swoole\Coroutine::sleep()
 * - Default: usleep()
 *
 * @param int $milliseconds Delay duration in milliseconds
 *
 * @psalm-suppress UndefinedClass
 */
function asyncDelay(int $milliseconds): void
{
    $seconds = $milliseconds / 1000;

    // Check Swoole coroutine context
    if (extension_loaded('swoole') && class_exists('Swoole\Coroutine') && Swoole\Coroutine::getCid() > 0) {
        Swoole\Coroutine::sleep($seconds);

        return;
    }

    // Default: blocking sleep
    usleep($milliseconds * 1000);
}
