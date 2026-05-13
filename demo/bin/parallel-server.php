<?php

declare(strict_types=1);

use BEAR\Async\Module\ParallelRuntimeModule;
use BEAR\Async\PendingRequests;
use BEAR\AsyncDemo\Injector;
use BEAR\Sunday\Extension\Application\AppInterface;

require dirname(__DIR__) . '/autoload.php';

if (! extension_loaded('parallel')) {
    fwrite(STDERR, "ext-parallel is not loaded\n");
    exit(1);
}

$host = getenv('BENCH_HOST') ?: '127.0.0.1';
$port = (int) (getenv('BENCH_PARALLEL_PORT') ?: getenv('BENCH_PORT') ?: 8081);
$context = getenv('APP_CONTEXT') ?: 'prod-hal-app';
$poolSize = (int) (getenv('PARALLEL_POOL_SIZE') ?: 8);
$workerCount = (int) (getenv('BENCH_PARALLEL_WORKERS') ?: 1);
$startupWarmup = getenv('BENCH_PARALLEL_STARTUP_WARMUP');
$sharedCacheWarmup = getenv('BENCH_PARALLEL_SHARED_CACHE_WARMUP');
$warmupPath = getenv('BENCH_PATH') ?: '/dashboard?user_id=1';

if ($port < 1) {
    fwrite(STDERR, "BENCH_PARALLEL_PORT must be a positive integer\n");
    exit(1);
}

if ($poolSize < 1) {
    fwrite(STDERR, "PARALLEL_POOL_SIZE must be a positive integer\n");
    exit(1);
}

if ($workerCount < 1) {
    fwrite(STDERR, "BENCH_PARALLEL_WORKERS must be a positive integer\n");
    exit(1);
}

if ($workerCount > 1 && ! extension_loaded('pcntl')) {
    fwrite(STDERR, "ext-pcntl is required when BENCH_PARALLEL_WORKERS is greater than 1\n");
    exit(1);
}

if (getenv('BEAR_ASYNC_PARALLEL_WARM_CACHE') === '1') {
    warmSharedCache($context, $poolSize, $warmupPath);

    exit(0);
}

if ($sharedCacheWarmup !== '0') {
    warmSharedCacheProcess();
}

$server = stream_socket_server(
    sprintf('tcp://%s:%d', $host, $port),
    $errno,
    $errstr,
);

if ($server === false) {
    fwrite(STDERR, sprintf("Failed to listen on %s:%d: %s (%d)\n", $host, $port, $errstr, $errno));
    exit(1);
}

stream_set_blocking($server, true);
fwrite(STDERR, sprintf(
    "BEAR.Async parallel benchmark server listening on http://%s:%d (context=%s, pool=%d, workers=%d)\n",
    $host,
    $port,
    $context,
    $poolSize,
    $workerCount,
));

if ($workerCount === 1) {
    runWorker($server, 1, $context, $poolSize, $startupWarmup !== '0', $warmupPath);

    exit(0);
}

runMaster($server, $workerCount, $context, $poolSize, $startupWarmup !== '0', $warmupPath);

/**
 * @param resource $connection
 */
function handleConnection($connection, AppInterface $app, PendingRequests $pendingRequests): bool
{
    $requestLine = fgets($connection);
    if ($requestLine === false) {
        return false;
    }

    $headers = [];
    while (($line = fgets($connection)) !== false) {
        $line = trim($line);
        if ($line === '') {
            break;
        }

        $separator = strpos($line, ':');
        if ($separator !== false) {
            $headers[strtolower(substr($line, 0, $separator))] = trim(substr($line, $separator + 1));
        }
    }

    $parts = explode(' ', trim($requestLine), 3);
    $version = $parts[2] ?? 'HTTP/1.0';
    $connectionHeader = strtolower($headers['connection'] ?? '');
    $keepAlive = ($version === 'HTTP/1.1' && $connectionHeader !== 'close') || $connectionHeader === 'keep-alive';
    if (count($parts) < 2) {
        sendResponse($connection, 400, 'Bad Request', "Bad Request\n", keepAlive: false);

        return false;
    }

    [$method, $target] = $parts;
    if ($method !== 'GET') {
        sendResponse($connection, 405, 'Method Not Allowed', "Method Not Allowed\n", keepAlive: $keepAlive);

        return $keepAlive;
    }

    $resourceUri = targetToResourceUri($target);
    if ($resourceUri === null) {
        sendResponse($connection, 400, 'Bad Request', "Bad Request\n", keepAlive: $keepAlive);

        return $keepAlive;
    }

    try {
        // PendingRequests outlives a single request inside this benchmark
        // harness because the injector is built once per worker. Resetting
        // it here keeps each measurement free of cache hits from the
        // previous call — production PHP-FPM gets a fresh injector per
        // request, so this reset is benchmark-only.
        $pendingRequests->reset();
        $start = hrtime(true);
        $response = $app->resource->get->uri($resourceUri)->eager->request();
        $body = (string) $response;
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        sendResponse($connection, $response->code, reasonPhrase($response->code), $body, [
            'Content-Type' => $response->headers['Content-Type'] ?? 'application/hal+json',
            'Cache-Control' => 'no-store',
            'X-Benchmark-Time-Ms' => sprintf('%.3f', $elapsedMs),
        ], $keepAlive);
    } catch (Throwable $e) {
        $body = json_encode([
            'error' => $e->getMessage(),
            'uri' => $resourceUri,
        ], JSON_UNESCAPED_SLASHES);
        sendResponse($connection, 500, 'Internal Server Error', ($body ?: '{"error":"unknown"}') . "\n", [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-store',
        ], $keepAlive);
    }

    return $keepAlive;
}

/**
 * @param resource              $connection
 * @param array<string, string> $headers
 */
function sendResponse($connection, int $code, string $reason, string $body, array $headers = [], bool $keepAlive = false): void
{
    $headers += [
        'Content-Type' => 'text/plain; charset=utf-8',
        'Cache-Control' => 'no-store',
    ];
    $headers['Content-Length'] = (string) strlen($body);
    $headers['Connection'] = $keepAlive ? 'keep-alive' : 'close';

    fwrite($connection, sprintf("HTTP/1.1 %d %s\r\n", $code, $reason));
    foreach ($headers as $name => $value) {
        fwrite($connection, sprintf("%s: %s\r\n", $name, $value));
    }

    fwrite($connection, "\r\n");
    fwrite($connection, $body);
}

function reasonPhrase(int $code): string
{
    return match ($code) {
        200 => 'OK',
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
        default => 'OK',
    };
}

/**
 * @param resource $server
 */
function runMaster($server, int $workerCount, string $context, int $poolSize, bool $startupWarmup, string $warmupPath): void
{
    pcntl_async_signals(true);

    $children = [];
    $shutdown = static function (int $exitCode = 0) use (&$children): never {
        foreach (array_keys($children) as $pid) {
            posix_kill((int) $pid, SIGTERM);
        }

        $deadline = microtime(true) + 2.0;
        while ($children !== [] && microtime(true) < $deadline) {
            $pid = pcntl_wait($status, WNOHANG);
            if ($pid > 0) {
                unset($children[$pid]);
                continue;
            }

            usleep(10_000);
        }

        foreach (array_keys($children) as $pid) {
            posix_kill((int) $pid, SIGKILL);
        }

        while ($children !== []) {
            $pid = pcntl_wait($status);
            if ($pid <= 0) {
                break;
            }

            unset($children[$pid]);
        }

        exit($exitCode);
    };

    pcntl_signal(SIGTERM, static fn() => $shutdown(0));
    pcntl_signal(SIGINT, static fn() => $shutdown(0));

    for ($workerId = 1; $workerId <= $workerCount; $workerId++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            fwrite(STDERR, "Failed to fork benchmark worker\n");
            $shutdown(1);
        }

        if ($pid === 0) {
            runWorker($server, $workerId, $context, $poolSize, $startupWarmup, $warmupPath);
            exit(0);
        }

        $children[$pid] = $workerId;
    }

    while ($children !== []) {
        $pid = pcntl_wait($status);
        if ($pid > 0) {
            fwrite(STDERR, sprintf("parallel benchmark worker %d exited\n", $children[$pid] ?? 0));
            unset($children[$pid]);
            if ($children !== []) {
                $shutdown(1);
            }
        }
    }
}

/**
 * @param resource $server
 */
function runWorker($server, int $workerId, string $context, int $poolSize, bool $startupWarmup, string $warmupPath): void
{
    if (extension_loaded('pcntl')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static fn() => exit(0));
        pcntl_signal(SIGINT, static fn() => exit(0));
    }

    $injector = Injector::getOverrideInstance(
        $context,
        new ParallelRuntimeModule($context, $poolSize),
    );
    $app = $injector->getInstance(AppInterface::class);
    $pendingRequests = $injector->getInstance(PendingRequests::class);

    if ($startupWarmup) {
        warmWorker($app, $pendingRequests, $warmupPath);
    }

    fwrite(STDERR, sprintf("parallel benchmark worker %d ready\n", $workerId));

    while (($connection = @stream_socket_accept($server, -1)) !== false) {
        do {
            $keepAlive = handleConnection($connection, $app, $pendingRequests);
        } while ($keepAlive && ! feof($connection));

        fclose($connection);
    }
}

function warmWorker(AppInterface $app, PendingRequests $pendingRequests, string $target): void
{
    $resourceUri = targetToResourceUri($target);
    if ($resourceUri === null) {
        throw new RuntimeException(sprintf('Invalid warmup path: %s', $target));
    }

    $pendingRequests->reset();
    $response = $app->resource->get->uri($resourceUri)->eager->request();
    (string) $response;
    $pendingRequests->reset();
}

function warmSharedCacheProcess(): void
{
    $env = getenv();
    $env['BEAR_ASYNC_PARALLEL_WARM_CACHE'] = '1';
    $env['BENCH_PARALLEL_WORKERS'] = '1';
    $env['BENCH_PARALLEL_SHARED_CACHE_WARMUP'] = '0';

    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __FILE__],
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        null,
        $env,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start shared cache warmup process');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $status = proc_close($process);
    if ($status !== 0) {
        fwrite(STDERR, "Shared DI/cache warmup failed before forking benchmark workers.\n");
        if ($stdout !== false && $stdout !== '') {
            fwrite(STDERR, $stdout);
        }

        if ($stderr !== false && $stderr !== '') {
            fwrite(STDERR, $stderr);
        }

        exit($status);
    }
}

function warmSharedCache(string $context, int $poolSize, string $warmupPath): void
{
    $injector = Injector::getOverrideInstance(
        $context,
        new ParallelRuntimeModule($context, $poolSize),
    );
    $app = $injector->getInstance(AppInterface::class);
    $pendingRequests = $injector->getInstance(PendingRequests::class);

    warmWorker($app, $pendingRequests, $warmupPath);
}

function targetToResourceUri(string $target): string|null
{
    $url = parse_url($target);
    if (! is_array($url)) {
        return null;
    }

    $path = $url['path'] ?? '/';
    if ($path === '/') {
        $path = '/dashboard';
    }

    $query = isset($url['query']) ? '?' . $url['query'] : '';

    return 'app://self' . $path . $query;
}
