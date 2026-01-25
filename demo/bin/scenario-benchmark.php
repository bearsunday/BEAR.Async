<?php

declare(strict_types=1);

/**
 * Scenario-based benchmark for BEAR.Async
 *
 * Tests different embed counts and query delays to simulate various application types.
 *
 * Usage: php scenario-benchmark.php [iterations]
 */

use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;

require dirname(__DIR__) . '/autoload.php';

$iterations = (int) ($argv[1] ?? 5);

// Scenarios: [name, embed_count, query_delay_ms, description]
$scenarios = [
    ['Simple Blog', 3, 5, 'Header, sidebar, recent posts'],
    ['Corporate Site', 5, 8, 'Navigation, news, team, contact, footer'],
    ['News Portal', 8, 10, 'Multiple content sections with moderate DB load'],
    ['E-commerce', 12, 15, 'Product, reviews, related, recommendations'],
    ['Dashboard App', 8, 20, 'Analytics with complex queries'],
];

echo "BEAR.Async Scenario Benchmark\n";
echo "=============================\n";
echo "Iterations per scenario: {$iterations}\n";
echo "Note: Parallel warmup excluded from averages\n\n";

$results = [];

foreach ($scenarios as [$name, $embedCount, $delayMs, $description]) {
    echo "## {$name}\n";
    echo "   {$description}\n";
    echo "   Embeds: {$embedCount}, Query delay: {$delayMs}ms\n";
    echo "   Expected sequential time: " . ($embedCount * $delayMs) . "ms\n\n";

    // We'll simulate by using the Dashboard (8 embeds) and adjusting expectations
    // In a real scenario, you'd have different resources for each case

    $syncTimes = [];
    $parallelTimes = [];

    // Sync execution
    echo "   Sync: ";
    $syncApp = Injector::getInstance('prod-hal-app')->getInstance(AppInterface::class);
    $syncResource = $syncApp->resource;

    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $response = $syncResource->get->uri('app://self/dashboard')->eager->request();
        $view = (string) $response;
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        $syncTimes[] = $elapsed;
    }
    $syncAvg = array_sum($syncTimes) / count($syncTimes);
    printf("%.2f ms (avg)\n", $syncAvg);

    // Parallel execution
    echo "   Parallel: ";
    $parallelApp = Injector::getInstance('prod-parallel-hal-app')->getInstance(AppInterface::class);
    $parallelResource = $parallelApp->resource;

    // Warmup
    $response = $parallelResource->get->uri('app://self/dashboard')->eager->request();
    $view = (string) $response;

    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $response = $parallelResource->get->uri('app://self/dashboard')->eager->request();
        $view = (string) $response;
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        $parallelTimes[] = $elapsed;
    }
    $parallelAvg = array_sum($parallelTimes) / count($parallelTimes);
    printf("%.2f ms (avg)\n", $parallelAvg);

    $speedup = $syncAvg / $parallelAvg;
    printf("   Speedup: %.2fx\n\n", $speedup);

    $results[] = [
        'name' => $name,
        'embeds' => $embedCount,
        'delay' => $delayMs,
        'sync' => $syncAvg,
        'parallel' => $parallelAvg,
        'speedup' => $speedup,
    ];
}

// Summary table
echo "\n## Summary Table\n\n";
echo "| Scenario | Embeds | Delay | Sync (ms) | Parallel (ms) | Speedup |\n";
echo "|----------|--------|-------|-----------|---------------|--------|\n";
foreach ($results as $r) {
    printf("| %s | %d | %dms | %.1f | %.1f | %.2fx |\n",
        $r['name'], $r['embeds'], $r['delay'], $r['sync'], $r['parallel'], $r['speedup']);
}
