# Changelog

All notable changes to this project will be documented in this file.

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
