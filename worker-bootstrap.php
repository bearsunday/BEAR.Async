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
 */
require __DIR__ . '/../../autoload.php';
