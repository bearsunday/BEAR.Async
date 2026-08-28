#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * BEAR.Async Adapter Benchmark
 *
 * Usage: php demo/benchmark.php [sync|swoole]
 *
 * Expected request tree (7 total requests):
 *   SlowUser (200ms)
 *   ├── SlowPosts[user_id=1] (200ms)
 *   │   ├── SlowComments[post_id=10] (200ms)
 *   │   └── SlowComments[post_id=11] (200ms)
 *   └── SlowPosts[user_id=2] (200ms)
 *       ├── SlowComments[post_id=20] (200ms)
 *       └── SlowComments[post_id=21] (200ms)
 *
 * Expected timings:
 *   - Sync:  7 requests × 200ms = ~1400ms (sequential)
 *   - Async: 3 levels × 200ms   = ~600ms  (parallel per level)
 */

use BEAR\Async\Module\AsyncSwooleModule;
use Ray\Di\AbstractModule;

require __DIR__ . '/bootstrap.php';

$adapterName = $argv[1] ?? 'sync';

if (! in_array($adapterName, ['sync', 'swoole'], true)) {
    echo json_encode(['error' => "Unknown adapter: {$adapterName}"]) . PHP_EOL;
    $GLOBALS['benchmark_exit_code'] = 1;
    if (! class_exists('Swoole\Coroutine') || Swoole\Coroutine::getCid() === -1) {
        exit(1);
    }

    return;
}

// Check adapter availability before building any module:
// sync always works; swoole needs ext-swoole and a running coroutine context.
$isAvailable = $adapterName === 'sync'
    || ((extension_loaded('swoole') || extension_loaded('openswoole')) && Swoole\Coroutine::getCid() > 0);

if (! $isAvailable) {
    echo json_encode(['error' => "Adapter '{$adapterName}' is not available"]) . PHP_EOL;
    $GLOBALS['benchmark_exit_code'] = 2;
    if (! class_exists('Swoole\Coroutine') || Swoole\Coroutine::getCid() === -1) {
        exit(2); // Skip (not failure)
    }

    return;
}

/** @var AbstractModule|null $module */
$module = $adapterName === 'swoole' ? new AsyncSwooleModule() : null;

$start = microtime(true);

// Run crawl with specified adapter
if ($module !== null) {
    $resource = createResourceClient($module);
} else {
    // Sync mode: use ResourceModule without async
    $resource = (new Ray\Di\Injector(
        new BEAR\Resource\Module\ResourceModule('BEAR\Async\Demo'),
        __DIR__ . '/tmp'
    ))->getInstance(BEAR\Resource\ResourceInterface::class);
}

$result = $resource->get->uri('app://self/slow-user')->linkCrawl('tree')();

$elapsed = microtime(true) - $start;

$output = [
    'adapter' => $adapterName,
    'elapsed_ms' => (int) round($elapsed * 1000),
    'request_count' => 7, // 1 user + 2 posts + 4 comments
    'expected_sync_ms' => 1400,
    'expected_async_ms' => 600,
    'is_async' => $adapterName !== 'sync',
    'parallel_verified' => $adapterName !== 'sync' && $elapsed < 1.0,
];

echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;

// Avoid exit() in Swoole coroutine context (causes ExitException)
// Return exit code via global for wrapper scripts to handle
$GLOBALS['benchmark_exit_code'] = $output['parallel_verified'] || $adapterName === 'sync' ? 0 : 1;

// Only call exit() when running directly (not in Swoole coroutine)
if (! class_exists('Swoole\Coroutine') || Swoole\Coroutine::getCid() === -1) {
    exit($GLOBALS['benchmark_exit_code']);
}
