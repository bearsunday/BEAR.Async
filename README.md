# BEAR.Async

Async/parallel execution for BEAR.Resource `linkCrawl()`.

## Installation

```bash
composer require bear/async
```

## Usage

```php
use BEAR\Async\Adapter;
use BEAR\Async\Module\AsyncCrawlModule;
use Ray\Di\AbstractModule;

class AppModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new PackageModule());

        // Specify the adapter explicitly
        $this->install(new AsyncCrawlModule(Adapter::Swoole));
    }
}
```

## Available Adapters

| Adapter | Requirements | Description |
|---------|--------------|-------------|
| `Adapter::Swoole` | ext-swoole + coroutine context | Swoole coroutines with WaitGroup |
| `Adapter::Amp` | amphp/amp | Amp async/await pattern |
| `Adapter::Parallel` | ext-parallel + ZTS PHP | True parallel execution |
| `Adapter::Sync` | None (default) | Synchronous fallback |

## How It Works

The AsyncLinker replaces the standard Linker to enable parallel execution of crawl requests:

1. **Level-by-level execution**: Crawl requests are processed level by level
2. **Request deduplication**: Same requests are merged and executed only once
3. **Result caching**: Results are cached to avoid redundant requests

```text
Level 1: Users → all user requests execute in parallel
Level 2: Posts for each user → all post requests execute in parallel
Level 3: Comments for each post → all comment requests execute in parallel
```

## Requirements

- PHP 8.2+
- bear/resource ^1.17
- ray/di ^2.18

## Optional Dependencies

- ext-swoole: For Swoole coroutine support
- amphp/amp: For Amp async support
- ext-parallel: For true parallel execution
