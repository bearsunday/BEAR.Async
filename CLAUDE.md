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
- **Modules**: `ParallelRuntimeModule` (`@internal` bootstrap override), `ParallelModule` (`@internal` mechanism), `AsyncSwooleModule`
- **PdoPool/PdoPoolModule**: Connection pool for Swoole (coroutines share memory, need pooled PDO)

### How Parallel Execution Works

1. `AsyncLinker.linkCrawl()` collects all embed requests at each level
2. `RequestBatch` deduplicates requests by URI+query hash
3. `AsyncInterface` executes all tasks in parallel
4. Results are cached and distributed to all requesters

### ext-parallel entrypoint flow

```text
bin/async.php                              (user)
  └→ require vendor/bear/async/bootstrap.php
       └→ Injector::getOverrideInstance(name, context, appDir,
                ParallelRuntimeModule(context, poolSize))
            └→ AppModule + override
                  └→ ParallelRuntimeModule.configure()
                       ├→ bind(Context) to context
                       └→ install(ParallelModule(poolSize))
                            └→ ParallelModule.configure()
                                 ├→ guard: throws RecursiveWorkerSpawnException
                                 │         if WorkerResourceCache::isWorker()
                                 ├→ bind(PoolSize), bind(AsyncInterface)
                                 └→ install(AsyncEmbedModule)
```

Inside each `parallel\Runtime`, `worker-bootstrap.php` loads the autoloader;
`WorkerResourceCache::getOrInit($name, $context, $appDir)` lazily builds the
worker's own `ResourceInterface` via plain `BEAR\Package\Injector::getInstance`
(no override → no parallel bindings → no recursion).

### Module selection

- **ext-parallel (PHP-FPM / Apache)**: add `bin/async.php`; AppModule is unchanged
- **ext-swoole**: install `AsyncSwooleModule` in AppModule and run `bin/swoole.php`; requires `PdoPoolModule` for PDO

## Code Quality

- PHPStan: level max
- Psalm: errorLevel 1
- CI tests PHP 8.2 through 8.5

## Qualifiers

DI qualifier attributes live in `src/Qualifier/`: `Context`, `PoolSize`.
Application name and directory are read from the injected `AbstractAppMeta`.
