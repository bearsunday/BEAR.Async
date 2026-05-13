# BEAR.Async

Async/parallel resource execution library for BEAR.Sunday

## Why BEAR.Async?

BEAR.Async preserves your resource code. You choose an async execution mode at the application boundary — no async/await, no Promise, no yield, no rewrites of existing `#[Embed]` graphs.

```php
#[Embed(rel: 'profile', src: 'app://self/user/profile?id={user_id}')]
#[Embed(rel: 'posts', src: 'app://self/user/posts?user_id={user_id}')]
#[Embed(rel: 'notifications', src: 'app://self/notifications?user_id={user_id}')]
public function onGet(int $user_id): static
```

These 3 embeds execute **in parallel** instead of sequentially.

## Installation

```bash
composer require bear/async
```

## Demo and Benchmarks

A runnable demo application lives in [`demo/`](demo/). It builds separate
Docker images for ext-parallel and ext-swoole, starts MySQL, seeds a dashboard
resource graph with 8 independent SQL-backed GET embeds, and exposes Sync,
ext-parallel, and Swoole entrypoints.

```bash
cd demo
docker compose up -d --wait parallel
docker compose exec parallel composer install
docker compose exec parallel composer app -- get 'app://self/dashboard?user_id=1'
docker compose exec parallel composer async -- get 'app://self/dashboard?user_id=1'
```

The demo also includes cold one-shot CLI benchmarks and steady-state HTTP
benchmarks with `wrk`:

```bash
docker compose exec parallel composer parallel-benchmark
docker compose exec parallel composer steady-state-parallel
docker compose up -d --wait swoole
docker compose exec swoole composer swoole-benchmark
docker compose exec swoole composer steady-state-swoole
```

See the [demo guide](demo/README.md) for setup details and
[benchmark results](docs/benchmark-results.md) for measured numbers and
adapter selection guidance.

## Execution Modes

### Parallel execution (ext-parallel)

Recommended for typical PHP-FPM / Apache web applications with embedded
resources.

Add `bin/async.php` next to `bin/app.php`. It hands off to the library
bootstrap, which overlays the ext-parallel runtime on the normal AppModule:

```text
bin/async.php → vendor/bear/async/bootstrap.php → AppModule + runtime overlay
```

```php
<?php // bin/async.php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

$bootstrap = dirname(__DIR__) . '/vendor/bear/async/bootstrap.php';
if (! file_exists($bootstrap)) {
    throw new LogicException('"bear/async" is not installed.');
}

$defaultContext = PHP_SAPI === 'cli' ? 'cli-hal-api-app' : 'hal-api-app';
$context = getenv('APP_CONTEXT') ?: $defaultContext;

exit((require $bootstrap)(
    $context,
    'MyVendor\MyApp',
    dirname(__DIR__),
    $GLOBALS,
    $_SERVER,
));
```

Do not install the parallel runtime in `AppModule` directly. The bootstrap
is the only supported install path so the same `AppModule` works under
`bin/app.php` (sync) and `bin/async.php` (parallel) unchanged.

To override the worker pool size (default = CPU cores), pass it as the
optional 6th argument:

```php
exit((require $bootstrap)($context, 'MyVendor\MyApp', dirname(__DIR__), $GLOBALS, $_SERVER, 8));
```

#### Constraints

Worker Runtimes are separate threads with their own zend memory. Embedded
resources executed via this module must satisfy:

- **Pure / idempotent** — same input must yield same output. Workers do not
  share request-scoped state (no shared session, no shared logger context).
- **Each worker holds its own DI container** — singletons in `AppModule`
  are not the same instance across threads. Avoid relying on
  "same-instance" guarantees inside parallelizable embeds.
- **Payload copyability** — arguments passed across the thread boundary
  (currently the `query` array) and return values (`$ro->body` or the
  rendered string) must be scalar / null / nested arrays of those.
  Objects, closures, and resources will fail fast via
  `NonCopyablePayloadException`.
- **Interceptors that mutate request-local state will misbehave** across
  worker boundaries. Keep cross-cutting concerns idempotent or scope them
  outside the parallelized embed graph.

#### Scope

This module targets PHP-FPM / Apache style request-per-process runtimes.
For long-running Swoole HTTP Server use `AsyncSwooleModule` instead — its
coroutines share the same process memory and do not have the cross-thread
copyability constraint.

### Swoole execution (ext-swoole)

For applications already running on Swoole HTTP Server with high concurrency
requirements.

ext-parallel uses worker runtimes, so it is selected by a separate entrypoint.
ext-swoole runs inside one server process, so it is installed as an application
module.

```php
use BEAR\Async\Module\AsyncSwooleModule;
use BEAR\Async\Module\PdoPoolEnvModule;
use Ray\Di\AbstractModule;

class AppModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());
        $this->install(new AsyncSwooleModule());
        $this->install(new PdoPoolEnvModule(
            'PDO_DSN',
            'PDO_USER',
            'PDO_PASSWORD',
        )); // Connection pool required
    }
}
```

## Which execution mode should I use?

| Use Case | Entrypoint | Runtime setup |
|---|---|---|
| PHP-FPM / Apache with embedded resources | `bin/async.php` | library bootstrap overlay |
| Swoole HTTP Server | `bin/swoole.php` | `AsyncSwooleModule` (in AppModule) |

### Comparison

| | ext-parallel | ext-swoole |
|---|---|---|
| Concurrency | Thread pool (CPU cores) | Coroutines (thousands) |
| Memory | Separate per worker | Shared (process-level) |
| PDO handling | Isolated per thread | Connection pool required |
| Server | PHP-FPM / Apache | Swoole HTTP Server |
| Setup | Add `bin/async.php` | Add `bin/swoole.php` |

## How It Works

The AsyncLinker replaces the standard Linker to enable parallel execution of resource requests:

1. **Level-by-level execution**: Requests are processed level by level
2. **Request deduplication**: Same requests are merged and executed only once
3. **Result caching**: Results are cached to avoid redundant requests

```text
Level 1: Users → all user requests execute in parallel
Level 2: Posts for each user → all post requests execute in parallel
Level 3: Comments for each post → all comment requests execute in parallel
```

## Documentation

- [Demo Guide](demo/README.md) - Docker-based demo for Sync, ext-parallel, and Swoole
- [Benchmark Results](docs/benchmark-results.md) - Measured cold CLI and steady-state HTTP results with adapter selection guidance
- [Parallel Execution Architecture and Performance Analysis](https://bearsunday.github.io/BEAR.Async/parallel-execution-architecture.html) - Deep dive into architecture, AWS instance recommendations, and cost savings projections

## Requirements

PHP 8.2+ for the library itself. Each execution mode adds its own runtime
requirement:

| Mode | Requires | Application change |
|---|---|---|
| ext-parallel | ZTS PHP + ext-parallel | add `bin/async.php` |
| ext-swoole | ext-swoole | install `AsyncSwooleModule`, use `bin/swoole.php` |

## Mysqli Batch Execution

Execute multiple SQL queries in parallel using mysqli's native async support.

### Installation

```php
use BEAR\Async\Module\MysqliBatchModule;

class AppModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new MysqliBatchModule(
            host: 'localhost',
            user: 'root',
            pass: 'password',
            database: 'mydb',
        ));
    }
}
```

Or with environment variables:

```php
use BEAR\Async\Module\MysqliEnvModule;

$this->install(new MysqliEnvModule(
    'MYSQLI_HOST',
    'MYSQLI_USER',
    'MYSQLI_PASSWORD',
    'MYSQLI_DATABASE',
));
```

### Usage

```php
use BEAR\Async\SqlBatch;
use BEAR\Async\SqlBatchExecutorInterface;

class MyService
{
    public function __construct(
        private SqlBatchExecutorInterface $executor,
    ) {}

    public function getData(int $userId): array
    {
        // Execute multiple queries in parallel with invocable pattern
        $results = (new SqlBatch($this->executor, [
            'user' => ['SELECT * FROM users WHERE id = :id', ['id' => $userId]],
            'posts' => ['SELECT * FROM posts WHERE user_id = :user_id', ['user_id' => $userId]],
            'comments' => ['SELECT * FROM comments WHERE user_id = :user_id', ['user_id' => $userId]],
        ]))();

        return [
            'user' => $results['user'][0] ?? null,
            'posts' => $results['posts'],
            'comments' => $results['comments'],
        ];
    }
}
```

### Architecture

| Class | Description |
|-------|-------------|
| `SqlBatch` | Invocable value object with `__invoke()` for one-line execution |
| `SqlBatchExecutorInterface` | Stateless executor interface (singleton-safe) |
| `MysqliBatchExecutor` | Async execution using `mysqli_poll` |
| `SyncBatchExecutor` | Sequential execution for testing/fallback |
