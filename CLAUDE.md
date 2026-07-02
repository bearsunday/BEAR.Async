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
./vendor/bin/phpunit tests/PendingRequestsTest.php
```

## Architecture

BEAR.Async enables transparent parallel execution of BEAR.Sunday's `#[Embed]` resources by replacing two bindings: `LinkCrawlerInterface` (for `linkCrawl()` graphs) and `EmbedInterceptorInterface` (for `#[Embed]` attributes).

### Core Design

```
LinkCrawlerInterface (bear/resource)         EmbedInterceptorInterface (bear/resource)
       ↓ replaced by                                ↓ replaced by
AsyncLinkCrawler ──uses──→ AsyncInterface     AsyncEmbedInterceptor ──uses──→ PendingRequests
                                ↓ implemented by            (wraps embeds in AsyncRequest/DeferredRequest,
                   ┌────────────┼────────────┐              batches them, executes via AsyncInterface)
             ParallelAsync  SwooleAsync  SyncAsync
             (ext-parallel)  (ext-swoole) (fallback)
```

### Key Components

- **AsyncLinkCrawler**: Replaces `LinkCrawler`, executes `linkCrawl()` requests level-by-level in parallel
- **AsyncEmbedInterceptor** / **PendingRequests** / **AsyncRequest** / **DeferredRequest**: Parallelize `#[Embed]` resources — the interceptor wraps each embed in an `AsyncRequest`, `PendingRequests` collects them (そうめん流し方式) and dispatches the batch through `AsyncInterface` on first render
- **AsyncInterface**: Adapter interface for different async runtimes
- **Adapters**: `ParallelAsync` (thread pool), `SwooleAsync` (coroutines), `SyncAsync` (sequential)
- **Modules**: `ParallelRuntimeModule` (`@internal` bootstrap override), `ParallelModule` (`@internal` mechanism), `AsyncSwooleModule`, `AsyncEmbedModule` (binds `EmbedInterceptorInterface`, installed automatically by the above)
- **PdoPool/PdoPoolModule**, **RedisPool/RedisPoolModule**: Connection pools for Swoole (coroutines share memory, need pooled connections)

### How Parallel Execution Works

1. `AsyncLinkCrawler.crawl()` collects all `linkCrawl()` requests at each level via `RequestBatch`
2. `AsyncEmbedInterceptor` wraps each `#[Embed]` in an `AsyncRequest`/`DeferredRequest` and registers it with `PendingRequests`
3. `RequestBatch` deduplicates crawl requests by URI+query hash; `PendingRequests` dedupes embeds by request hash
4. `AsyncInterface` executes all tasks/requests in parallel
5. Results are cached and distributed to all requesters

### Failure semantics

A failing embed or crawl task never aborts its siblings or crashes the
Swoole worker process: every task/coroutine is allowed to finish, and only
after all of them complete is the first encountered exception rethrown to
the caller (a 500 for that one request, not the whole worker). `ParallelAsync`
follows the same pattern — every dispatched `parallel\Future` is joined
before the first `Throwable` is rethrown. There is deliberately no silent
fallback: if the required extension (`ext-parallel`/`ext-swoole`) is missing,
the owning module's `configure()` throws `ExtensionNotLoadedException`
immediately instead of degrading to `SyncAsync`.

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

- **ext-parallel (PHP-FPM / Apache)**: add `bin/async.php`; AppModule is unchanged. The `parallel\Runtime` pool lives in userland process state, so under classic PHP-FPM it is rebuilt on every request (thread spawn + autoload + container per worker). Steady-state benefits require a resident worker process that keeps the pool warm across requests (see `demo/bin/parallel-server.php`), not a fresh PHP-FPM process per request.
- **ext-swoole**: install `AsyncSwooleModule` in AppModule and run `bin/swoole.php`; requires `PdoPoolModule` for PDO

## Code Quality

- PHPStan: level max
- Psalm: errorLevel 1
- CI tests PHP 8.2 through 8.5

## Qualifiers

DI qualifier attributes live in `src/Qualifier/`: `Context`, `PoolSize`.
Application name and directory are read from the injected `AbstractAppMeta`.
