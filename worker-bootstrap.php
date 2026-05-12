<?php

declare(strict_types=1);

/**
 * Worker Runtime bootstrap for ext-parallel.
 *
 * Loaded by every `parallel\Runtime` instance when it is spawned. Its sole
 * responsibility is to expose the application's Composer autoloader inside
 * the worker's separate zend memory. The per-worker `ResourceInterface` is
 * built lazily on the first task via `WorkerResourceCache::getOrInit()`.
 *
 * Expected layout once installed: vendor/bear/async/worker-bootstrap.php
 *   → ../../autoload.php is the application's vendor/autoload.php.
 *
 * Source checkouts used as a Composer path repository resolve __DIR__ to the
 * package root, so the application bootstrap can pass BEAR_ASYNC_AUTOLOAD.
 */
$autoload = getenv('BEAR_ASYNC_AUTOLOAD');
if (is_string($autoload) && $autoload !== '' && is_file($autoload)) {
    require $autoload;

    return;
}

$autoload = __DIR__ . '/../../autoload.php';
if (is_file($autoload)) {
    require $autoload;

    return;
}

throw new RuntimeException('Unable to locate Composer autoload.php for BEAR.Async worker runtime.');
