# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Fixed

- Documented install order now works: install `AsyncSwooleModule` before `PackageModule` (Ray.Di keeps the first binding).
- `AsyncEmbedInterceptor` resolves `PendingRequests` per invocation via `ProviderInterface` instead of at construction, so the Swoole coroutine-local binding is no longer shared across coroutines; `SwoolePendingRequestsProvider` throws `Exception\NotInCoroutineException` outside a coroutine instead of returning disjoint instances.
- `AsyncLinkCrawler` scopes its dedup cache to a single `crawl()` call instead of the instance lifetime.
- Demo: `bin/swoole.php` boots with `BEAR\Package\Injector::getOverrideInstance()` and warms up the compiled DI container, fixing a race in the reflective injector that failed concurrent requests with a false `CircularDependency`.

### Changed

- `AsyncEmbedModule` binds `SyncAsync` as the default `AsyncInterface` adapter; runtime modules still win by binding their own adapter first.

## 0.4.0 - 2026-08-29

### BREAKING

- Removed `BEAR\Async\SqlBatch` and `BEAR\Async\SqlBatchExecutorInterface`.
- Removed mysqli-based async batch execution: `Mysqli\MysqliBatchExecutor`, `Mysqli\SyncBatchExecutor`, `Mysqli\MysqliConnectionFactory`, `Mysqli\MysqliParamBinder`.
- Removed `Module\MysqliBatchModule`, `Module\MysqliEnvModule`, and `Exception\MysqliConnectionException`.
- Removed the `BEAR\Projection\` namespace (`QueryBatchCoordinator`, `QueryResourceObject`, `Exception\SqlFileNotFoundException`).
- Migration: split each SQL into its own `ResourceObject` and let `#[Embed]` parallelize them. Pair with Ray.MediaQuery's `#[DbQuery]` BDR pattern for the underlying Repository. See "SQL Resources with BDR + `#[Embed]`" in `README.md`.
- Removed `AsyncInterface::isAvailable()`; a missing extension fails fast with `Exception\ExtensionNotLoadedException` from the owning module's `configure()` instead of falling back at runtime.
- Removed the dead `Async` facade, `AsyncLinker`, `Adapter\Linker`, and `Exception\PoolNotInitializedException`.
- `PendingRequests::getResult()` now takes the `AsyncRequest` instead of its URI.
- `PdoPoolEnvModule` / `RedisPoolEnvModule` throw `Exception\InvalidEnvException` at boot when a pool-size, borrow-timeout, port or DB-index variable is set to an invalid value (previously fell back to the default).

### Fixed

- `SwooleAsync` catches each coroutine's `Throwable`, so one failing embed no longer takes down the worker process; siblings finish and the first error is rethrown afterwards.
- `ParallelAsync` joins every `parallel\Future` before rethrowing, replays `linkSelf()`/`linkNew()`/`linkCrawl()` inside the worker, and reports never-dispatched tasks with `Exception\TaskNotDispatchedException`.
- `ParallelAsync` preserves the request method across the thread boundary instead of rebuilding every embed as `GET`.
- `ParallelAsync` no longer leaks runtime threads when pool warm-up fails and is retried.
- The error rethrown after a failed batch is the first in submission order for every adapter, not the first to finish; `SyncAsync` also runs all siblings to completion first.
- Pending embeds are deduplicated by request hash (method + URI + links) instead of URI alone, and `AsyncRequest` re-keys itself when `withQuery()`/`addQuery()`/`linkSelf()`/`linkNew()`/`linkCrawl()` change that hash.
- `AsyncRequest::hash()` no longer conflates different URIs served by the same `ResourceObject` class.
- Directly invoking an `AsyncRequest` (`__invoke()`, `offsetGet()`, iteration) completes its pending entry instead of executing it again on a later render; a failed batch is abandoned rather than replayed.

### Added

- `PooledPdoBorrower` — shared checkout for `PooledPdoProvider` / `PooledExtendedPdoProvider`: bounded wait (`Exception\PoolTimeoutException`), `SELECT 1` ping on checkout, one retry after discarding a dead connection, then `Exception\StalePooledConnectionException` with the driver error as the previous exception.
- `PooledRedisProvider` mirrors that checkout (bounded wait, `PING`, one retry) and caches the connection per coroutine, returning it once via `Coroutine::defer()`.
- `borrowTimeout` on `PdoPoolModule` / `RedisPoolModule` and `borrowTimeoutEnv` / `defaultBorrowTimeout` on `PdoPoolEnvModule` / `RedisPoolEnvModule` (default 5.0s; `pdo_pool_borrow_timeout` / `redis_pool_borrow_timeout` bindings).
- `Adapter\TaskErrors`, `Module\PoolEnv`, `Exception\InvalidEnvException`, `Exception\StalePooledConnectionException`, `Exception\TaskNotDispatchedException`.

### Changed

- Domain exceptions extend the package's `Exception\RuntimeException` (no longer `final`), so every BEAR.Async failure can be caught with one base type.
- `ParallelAsync` warms one worker synchronously before dispatching, so the rest of the pool reuses the compiled container instead of building it concurrently.

## 0.3.0 - 2026-05-13

### Fixed

- `AsyncRequest` now extends `AbstractRequest` so `HalRenderer` can recognize and resolve embedded async requests during JSON serialization (#19).
- Async embeds are batched during JSON serialization to avoid serial execution at render time.
- ext-parallel runtimes are released after each batch and shut down gracefully; the worker resource cache is reset before closing runtimes.

### Added

- `DeferredRequest` — defers `ResourceObject` construction until the embedded request is actually invoked. Avoids reserving Swoole PDO pool connections at embed wiring time.
- `PdoProxyExtractor` (internal) — extracts the wrapped `PDO` from Swoole's `PDOProxy` via a cached `ReflectionProperty`. Shared by `PooledPdoProvider` and `PooledExtendedPdoProvider`.
- `Exception\PdoProxyExtractionException` — surfaces Swoole `PDOProxy` reflection failures as a domain exception instead of a raw `ReflectionException`.
- Demo: Docker-based dev environment with separate images for ext-parallel and ext-swoole.
- Demo: steady-state HTTP benchmark harness (`bin/parallel-server.php`, `bin/steady-state-benchmark.sh`, `bin/steady-state-matrix.sh`) for measuring warm per-request cost instead of cold one-shot spawn.
- Demo: "When to choose parallel execution" guidance in `demo/README.md` and `docs/benchmark-results.md`.

### Changed

- `PooledPdoProvider` / `PooledExtendedPdoProvider` are now coroutine-local: a single `PDO` is shared within a coroutine across both providers, returned to the pool exactly once via `Coroutine::defer()`.
- `composer.json` `require`: `bear/resource` bumped from `^1.31` to `^1.32` (needed for async embed resolution in HAL serialization).
- Demo default database backend switched from SQLite to MySQL (connection pooling requires a real RDBMS).

## 0.2.0 - 2026-05-11

### Changed

- **BC break:** `AsyncParallelModule` has been replaced by internal `ParallelModule`; ext-parallel apps should use `bin/async.php` and the library `bootstrap.php` entrypoint instead of installing a module directly.
- ext-parallel execution is now triggered by an explicit `bin/async.php` entrypoint instead of a `parallel-` context prefix. AppModule no longer needs to know it is running in parallel.
- `ParallelAsync` no longer generates a bootstrap PHP file at runtime. Workers load `vendor/bear/async/worker-bootstrap.php` (a physical file) and build their `ResourceInterface` lazily via `WorkerResourceCache::getOrInit()`.
- README/index docs realigned around an "Execution Modes" axis: parallel is chosen via `bin/async.php`, swoole via `AsyncSwooleModule` in `AppModule`. The asymmetry (entrypoint vs. module) is now documented as intentional — worker runtimes vs. single-process coroutines.

### Added

- `bootstrap.php` (library top-level) returning a closure that builds an override injector with internal `ParallelRuntimeModule` and runs the standard request lifecycle. Use from `bin/async.php`.
- `worker-bootstrap.php` (library top-level) loaded by each `parallel\Runtime`.
- `ParallelRuntimeModule` — context-aware runtime override (`@internal`). Binds the `#[Context]` qualifier and installs `ParallelModule`.
- `Worker\WorkerResourceCache` — per-Runtime resource cache with worker marker and `name|context|appDir` key guard.
- `Worker\PayloadValidator::assertCopyable()` — validates args/return crossing the thread boundary are scalar / null / nested arrays.
- `Exception\RecursiveWorkerSpawnException`, `Exception\NonCopyablePayloadException`, `Exception\InconsistentWorkerContextException`.
- `composer.json` `require`: `bear/package ^1.14`, `bear/app-meta ^1.6`.

### Removed

- `Qualifier\AppNamespace`, `Qualifier\AppDir` — replaced by injecting `AbstractAppMeta` directly into `ParallelAsync`.
- `Exception\BootstrapFileException` — no bootstrap file is generated anymore.

## 0.1.0 - 2026-02-03

Initial release of BEAR.Async - transparent parallel execution for BEAR.Sunday.

### Added

#### Parallel Execution

- `AsyncLinker` replaces standard `LinkerInterface` for parallel `linkCrawl()` execution
- `AsyncLinkCrawler` implementing `LinkCrawlerInterface` for level-by-level parallel crawling
- `AsyncEmbedInterceptor` for parallel `#[Embed]` resource loading
- `PendingRequests` pattern with request deduplication via URI+query hash
- `RequestBatch` / `RequestTask` for batch execution management

#### Async Adapters

- `ParallelAsync` - Thread pool execution using ext-parallel (ZTS PHP)
- `SwooleAsync` - Coroutine-based execution using ext-swoole
- `SyncAsync` - Sequential fallback when no async extension is available

#### DI Modules

- `AsyncParallelModule` - DI configuration for ext-parallel environments (PHP-FPM/Apache)
- `AsyncSwooleModule` - DI configuration for Swoole HTTP Server environments
- `AsyncEmbedModule` - Parallel `#[Embed]` support (installed by both modules above)

#### Connection Pools (Swoole)

- `PdoPoolModule` / `PdoPoolEnvModule` - PDO connection pool using `Swoole\Database\PDOPool`
- `RedisPoolModule` / `RedisPoolEnvModule` - Redis connection pool using `Swoole\Database\RedisPool`
- `PooledPdoProvider` / `PooledExtendedPdoProvider` - Coroutine-safe PDO providers
- `PooledRedisProvider` - Coroutine-safe Redis provider

#### SQL Batch

- `SqlBatch` - Invocable batch SQL execution
- `MysqliBatchModule` / `MysqliEnvModule` - mysqli multi-query support

#### Other

- Domain-specific exceptions (`PoolTimeoutException`, `NotInCoroutineException`, etc.)
- Qualifier attributes for DI (`Context`, `PoolSize`)
- Demo application with Docker support and benchmark scripts
