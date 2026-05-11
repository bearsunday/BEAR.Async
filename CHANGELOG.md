# Changelog

All notable changes to this project will be documented in this file.

## Unreleased

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
