#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Swoole Benchmark Wrapper for xstep debugging
 *
 * xstep does not support `php -r` option, so this wrapper script
 * provides a file-based entry point for Swoole coroutine context.
 *
 * Usage:
 *   php demo/swoole-benchmark.php
 *
 * xstep debugging:
 *   ~/.composer/vendor/bin/xstep --break="src/Adapter/SwooleAsync.php:33" --steps=10 -- php demo/swoole-benchmark.php
 */

if (! extension_loaded('swoole')) {
    echo json_encode(['error' => 'Swoole extension not loaded']) . PHP_EOL;
    exit(1);
}

Co\run(static function (): void {
    global $argv;
    $argv = ['benchmark.php', 'swoole'];
    include __DIR__ . '/benchmark.php';
});

// Return exit code from benchmark
exit($GLOBALS['benchmark_exit_code'] ?? 0);
