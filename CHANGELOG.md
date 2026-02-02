# Changelog

All notable changes to this project will be documented in this file.

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
- Qualifier attributes for DI (`AppNamespace`, `Context`, `AppDir`, `PoolSize`)
- Demo application with Docker support and benchmark scripts
