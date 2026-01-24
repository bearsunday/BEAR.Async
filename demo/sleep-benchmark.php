#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * BEAR.Async Sleep Benchmark (No DB required)
 *
 * Benchmarks parallel #[Embed] execution using sleep-based I/O simulation.
 * Each of the 10 embedded resources has a 5ms delay.
 *
 * Usage:
 *   php demo/sleep-benchmark.php [mode]
 *
 * Modes:
 *   swoole   - Run Swoole benchmark (requires ext-swoole)
 *   parallel - Run ext-parallel benchmark (requires ext-parallel + ZTS)
 *   (none)   - Auto-detect and run available benchmarks
 *
 * Expected results:
 *   - Sync:     10 * 5ms = ~50ms (sequential)
 *   - Parallel: ~5ms (parallel, limited by longest single operation)
 *   - Speedup:  ~10x
 *
 * This benchmark is suitable for CI environments without MySQL.
 */

use BEAR\Async\Demo\Module\SlowDemoModule;
use BEAR\Async\Module\AsyncSwooleModule;
use BEAR\Resource\ResourceInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

require dirname(__DIR__) . '/vendor/autoload.php';

$mode = $argv[1] ?? null;

/**
 * Async-aware delay function
 */
function asyncDelay(int $milliseconds): void
{
    $seconds = $milliseconds / 1000;
    if (extension_loaded('swoole') && class_exists('Swoole\Coroutine') && Swoole\Coroutine::getCid() > 0) {
        Swoole\Coroutine::sleep($seconds);
        return;
    }
    usleep($milliseconds * 1000);
}

/**
 * Create resource client with specified module
 */
function createResourceClient(?AbstractModule $asyncModule = null): ResourceInterface
{
    $module = new SlowDemoModule();
    if ($asyncModule !== null) {
        $module->override($asyncModule);
    }
    return (new Injector($module))->getInstance(ResourceInterface::class);
}

/**
 * Run ext-parallel benchmark
 *
 * Note: ParallelAsync is designed for BEAR.Package applications.
 * This benchmark tests ext-parallel directly to verify the extension works.
 */
function runParallelBenchmark(float $syncAvg): void
{
    echo "\n--- ext-parallel Mode ---\n";
    echo "Testing ext-parallel execution with 10 parallel 5ms delays...\n\n";

    // Test parallel execution directly using ext-parallel
    $taskCount = 10;
    $delayMs = 5;

    // Warmup
    $runtime = new parallel\Runtime();
    $future = $runtime->run(static function () {
        usleep(1000);
        return true;
    });
    $future->value();

    // Benchmark parallel execution
    $parallelTimes = [];
    for ($run = 0; $run < 5; $run++) {
        $runtimes = [];
        $futures = [];

        $start = microtime(true);
        for ($i = 0; $i < $taskCount; $i++) {
            $runtimes[$i] = new parallel\Runtime();
            $futures[$i] = $runtimes[$i]->run(static function (int $delayMs): int {
                usleep($delayMs * 1000);
                return $delayMs;
            }, [$delayMs]);
        }

        // Wait for all to complete
        foreach ($futures as $future) {
            $future->value();
        }
        $parallelTimes[] = (microtime(true) - $start) * 1000;

        // Cleanup runtimes
        foreach ($runtimes as $rt) {
            $rt->kill();
        }
    }

    $parallelAvg = array_sum($parallelTimes) / count($parallelTimes);
    printf("ext-parallel average (5 runs): %.1fms\n", $parallelAvg);

    echo "\n=== Results ===\n";
    printf("Sync:         %.1fms\n", $syncAvg);
    printf("ext-parallel: %.1fms\n", $parallelAvg);
    printf("Speedup:      %.1fx\n", $syncAvg / $parallelAvg);

    // Validate expected behavior
    $expectedSync = 50;
    $expectedParallel = 5;
    $tolerance = 0.5;

    $syncOk = $syncAvg >= $expectedSync * (1 - $tolerance) && $syncAvg <= $expectedSync * (1 + $tolerance);
    $parallelOk = $parallelAvg >= $expectedParallel * (1 - $tolerance) && $parallelAvg <= $expectedParallel * 4;

    $passed = $syncOk && $parallelOk && $syncAvg / $parallelAvg >= 3;
    $message = $passed
        ? "\nBenchmark passed: ext-parallel execution is working correctly.\n"
        : "\nBenchmark failed: Results outside expected range.\n";
    echo $message;
}

$hasSwoole = extension_loaded('swoole');
$hasParallel = extension_loaded('parallel');

echo "=== Sleep Benchmark - #[Embed] (No DB) ===\n\n";
echo "Embedded resources: 10\n";
echo "Delay per resource: 5ms\n";
echo "Expected sequential: 50ms\n";
echo "Expected parallel:   5ms\n\n";

// Sync benchmark (using standard module, no async)
echo "--- Sync Mode ---\n";
$syncResource = createResourceClient();

// Warmup
(string) $syncResource->get->uri('app://self/slow/dashboard')->eager->request();

// Measure 5 runs
$syncTimes = [];
for ($i = 0; $i < 5; $i++) {
    $start = microtime(true);
    (string) $syncResource->get->uri('app://self/slow/dashboard')->eager->request();
    $syncTimes[] = (microtime(true) - $start) * 1000;
}
$syncAvg = array_sum($syncTimes) / count($syncTimes);
printf("Sync average (5 runs): %.1fms\n", $syncAvg);

// Handle specific mode request
if ($mode === 'parallel') {
    if (! $hasParallel) {
        echo "\nError: ext-parallel is not available.\n";
        exit(1);
    }
    runParallelBenchmark($syncAvg);
    exit(0);
}

if ($mode === 'swoole') {
    if (! $hasSwoole) {
        echo "\nError: Swoole extension is not available.\n";
        exit(1);
    }
    Co\run(function () use ($syncAvg) {
        echo "\n--- Swoole + AsyncSwooleModule Mode ---\n";

        $swooleResource = createResourceClient(new AsyncSwooleModule());

        // Warmup
        (string) $swooleResource->get->uri('app://self/slow/dashboard')->eager->request();

        // Measure 5 runs
        $swooleTimes = [];
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            (string) $swooleResource->get->uri('app://self/slow/dashboard')->eager->request();
            $swooleTimes[] = (microtime(true) - $start) * 1000;
        }
        $swooleAvg = array_sum($swooleTimes) / count($swooleTimes);
        printf("Swoole average (5 runs): %.1fms\n", $swooleAvg);

        echo "\n=== Results ===\n";
        printf("Sync:    %.1fms\n", $syncAvg);
        printf("Swoole:  %.1fms\n", $swooleAvg);
        printf("Speedup: %.1fx\n", $syncAvg / $swooleAvg);

        $expectedSync = 50;
        $expectedSwoole = 5;
        $tolerance = 0.5;

        $syncOk = $syncAvg >= $expectedSync * (1 - $tolerance) && $syncAvg <= $expectedSync * (1 + $tolerance);
        $swooleOk = $swooleAvg >= $expectedSwoole * (1 - $tolerance) && $swooleAvg <= $expectedSwoole * 3;

        $passed = $syncOk && $swooleOk && $syncAvg / $swooleAvg >= 3;
        $message = $passed
            ? "\nBenchmark passed: Parallel #[Embed] execution is working correctly.\n"
            : "\nBenchmark failed: Results outside expected range.\n";
        echo $message;
    });
    exit(0);
}

// Auto-detect mode: run available benchmarks
if ($hasParallel) {
    runParallelBenchmark($syncAvg);
}

if ($hasSwoole) {
    Co\run(function () use ($syncAvg) {
        echo "\n--- Swoole + AsyncSwooleModule Mode ---\n";

        $swooleResource = createResourceClient(new AsyncSwooleModule());

        // Warmup
        (string) $swooleResource->get->uri('app://self/slow/dashboard')->eager->request();

        // Measure 5 runs
        $swooleTimes = [];
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            (string) $swooleResource->get->uri('app://self/slow/dashboard')->eager->request();
            $swooleTimes[] = (microtime(true) - $start) * 1000;
        }
        $swooleAvg = array_sum($swooleTimes) / count($swooleTimes);
        printf("Swoole average (5 runs): %.1fms\n", $swooleAvg);

        echo "\n=== Results ===\n";
        printf("Sync:    %.1fms\n", $syncAvg);
        printf("Swoole:  %.1fms\n", $swooleAvg);
        printf("Speedup: %.1fx\n", $syncAvg / $swooleAvg);

        $expectedSync = 50;
        $expectedSwoole = 5;
        $tolerance = 0.5;

        $syncOk = $syncAvg >= $expectedSync * (1 - $tolerance) && $syncAvg <= $expectedSync * (1 + $tolerance);
        $swooleOk = $swooleAvg >= $expectedSwoole * (1 - $tolerance) && $swooleAvg <= $expectedSwoole * 3;

        $passed = $syncOk && $swooleOk && $syncAvg / $swooleAvg >= 3;
        $message = $passed
            ? "\nBenchmark passed: Parallel #[Embed] execution is working correctly.\n"
            : "\nBenchmark failed: Results outside expected range.\n";
        echo $message;
    });
}

if (! $hasParallel && ! $hasSwoole) {
    echo "\nNo parallel extensions available (ext-swoole or ext-parallel).\n";
}
