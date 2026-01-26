# BEAR.Async

Async/parallel resource execution library for BEAR.Sunday

## Why BEAR.Async?

Unlike traditional async programming (async/await, Promise, yield), BEAR.Async requires **no code changes**. Your existing `#[Embed]` attributes automatically execute in parallel - just install a module.

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

## Usage

### Parallel Module (ext-parallel)

Recommended for typical web applications with embedded resources.

```php
use BEAR\Async\Module\AsyncParallelModule;
use Ray\Di\AbstractModule;

class AppModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());
        $this->install(new AsyncParallelModule(
            namespace: 'MyVendor\MyApp',
            context: 'prod-app',
            appDir: dirname(__DIR__),
        ));
    }
}
```

Pool size defaults to CPU core count. To override:

```php
$this->install(new AsyncParallelModule(
    namespace: 'MyVendor\MyApp',
    context: 'prod-app',
    appDir: dirname(__DIR__),
    poolSize: 8,
));
```

### Swoole Module (ext-swoole)

For applications already running on Swoole HTTP Server with high concurrency requirements.

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

## Which Module Should I Use?

| Use Case | Recommended Module |
|----------|-------------------|
| PHP-FPM / Apache with embedded resources | `AsyncParallelModule` |
| Swoole HTTP Server | `AsyncSwooleModule` |

### Comparison

| | AsyncParallelModule | AsyncSwooleModule |
|---|---|---|
| Concurrency | Thread pool (CPU cores) | Coroutines (thousands) |
| PDO handling | Isolated per thread | Connection pool required |
| Server | PHP-FPM / Apache | Swoole HTTP Server |
| Setup | Simple | Requires Swoole server |

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

- [Parallel Execution Architecture and Performance Analysis](https://bearsunday.github.io/BEAR.Async/parallel-execution-architecture.html) - Deep dive into architecture, AWS instance recommendations, and cost savings projections

## Requirements

- PHP 8.2+
- ext-parallel (ZTS PHP required) or ext-swoole for async execution

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
