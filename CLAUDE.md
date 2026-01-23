# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build Commands

```bash
composer test          # Run unit tests
composer tests         # Run CS + static analysis + tests (use before PR)
composer cs-fix        # Fix coding standards (use before commit)
composer sa            # Run PHPStan + Psalm
```

### Single Test

```bash
./vendor/bin/phpunit --filter testMethodName
./vendor/bin/phpunit tests/AsyncTest.php
```

## Architecture

BEAR.Async enables transparent parallel execution of BEAR.Sunday's `#[Embed]` resources by replacing the `LinkerInterface` implementation.

### Core Design

```
LinkerInterface (bear/resource)
       ↓ replaced by
AsyncLinker ──uses──→ AsyncInterface
                           ↓ implemented by
              ┌────────────┼────────────┐
        ParallelAsync  SwooleAsync  SyncAsync
        (ext-parallel)  (ext-swoole) (fallback)
```

### Key Components

- **AsyncLinker**: Replaces standard Linker, executes crawl requests level-by-level in parallel
- **AsyncInterface**: Adapter interface for different async runtimes
- **Adapters**: `ParallelAsync` (thread pool), `SwooleAsync` (coroutines), `SyncAsync` (sequential)
- **Modules**: `AsyncParallelModule`, `AsyncSwooleModule` - DI configuration for each adapter
- **PdoPool/PdoPoolModule**: Connection pool for Swoole (coroutines share memory, need pooled PDO)

### How Parallel Execution Works

1. `AsyncLinker.linkCrawl()` collects all embed requests at each level
2. `RequestBatch` deduplicates requests by URI+query hash
3. `AsyncInterface` executes all tasks in parallel
4. Results are cached and distributed to all requesters

### Module Selection

- **AsyncParallelModule**: PHP-FPM/Apache, each thread has isolated PDO (no pool needed)
- **AsyncSwooleModule**: Swoole HTTP Server, requires `PdoPoolModule` for PDO

## Code Quality

- PHPStan: level max
- Psalm: errorLevel 1
- CI tests PHP 8.2 through 8.5

## Qualifiers

DI bindings for `AsyncParallelModule` use Qualifier attributes in `src/Qualifier/`:
- `AppNamespace`, `Context`, `AppDir`, `PoolSize`
