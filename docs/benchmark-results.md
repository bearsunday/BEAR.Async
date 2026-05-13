---
layout: default
title: Benchmark Results
---

# Benchmark Results

This document presents benchmark results comparing synchronous execution with parallel execution using ext-parallel and ext-swoole.

## Environment

- **Container**: Docker (php:8.4-zts)
- **Extensions**: ext-parallel, ext-swoole
- **Database**: MySQL 8.0
- **CPU**: Host machine CPU (parallelism depends on available cores)

## Running Benchmarks

```bash
cd demo
docker compose up -d --wait parallel
docker compose exec parallel composer install

# Cold one-shot CLI reference
docker compose exec parallel composer parallel-benchmark

# Steady-state HTTP benchmark with wrk
docker compose exec parallel composer steady-state-parallel
composer steady-state-matrix

# Swoole uses the separate swoole image
docker compose up -d --wait swoole
docker compose exec swoole composer install
docker compose exec swoole composer swoole-benchmark
docker compose exec swoole composer steady-state-swoole
```

## Simulation Methodology

Each embedded resource uses `SlowQueryInterceptor` to add artificial delay,
simulating realistic I/O latency. This approach provides reproducible
benchmarks that reflect real-world conditions:

| Operation | Typical Latency |
|-----------|-----------------|
| Simple SELECT | 1-5ms |
| JOIN/Aggregation | 10-50ms |
| Network latency (RDS) | 1-10ms |
| Template processing | 1-10ms |

The default 10ms delay represents a conservative, realistic per-resource
overhead combining SQL execution, network latency, and processing time.

The demo now separates two benchmark profiles:

- **Cold one-shot CLI**: includes startup work such as DI lookup and, for
  ext-parallel, one-time `parallel\Runtime` spawn.
- **Steady-state HTTP**: starts a long-running server and uses `wrk` to issue
  repeated HTTP requests after the server is ready and warmed. Request-local
  async result caches are reset between requests.

The demo ext-parallel HTTP server is a multi-process harness. Each HTTP worker
process keeps its own long-running `parallel\Runtime` pool, so higher
`WRK_CONNECTIONS` values can be used for HTTP concurrency measurements. Swoole
uses its actual long-running HTTP server.

You can verify the cold CLI benchmark path in our [CI benchmark workflow](https://github.com/bearsunday/BEAR.Async/actions/workflows/async-benchmark.yml), which runs on every push and pull request.

## When to Choose Parallel

For a read-only resource graph that embeds multiple independent GET resources,
parallel execution should be the first candidate when the runtime supports it
and the downstream database or API capacity is sized for the extra concurrency.
This is the core design point of BEAR.Async: application code declares the
resource graph with `#[Embed]`, and the Linker implementation decides whether
the graph is resolved sequentially, with Swoole coroutines, or with
ext-parallel workers.

### Preconditions

- Embedded resources are read-only GET resources with no ordering dependency.
- The runtime extension is available: `ext-swoole` or `ext-parallel`.
- Downstream capacity is sized for internal parallelism, not just HTTP request
  concurrency.
- Swoole uses a coroutine-aware connection pool. If the goal is to avoid
  queueing, size `PDO_POOL_SIZE` for roughly `embed_count * request_concurrency`.
  A smaller pool is still valid when you intentionally want backpressure.
- ext-parallel uses a `parallel\Runtime` pool per resident HTTP worker process.
  To run all embeds in one dashboard request concurrently, set
  `PARALLEL_POOL_SIZE >= embed_count` for each worker. Database connections
  grow with the number of HTTP workers and their runtime pools.
- ext-parallel steady-state measurements require a process that keeps the
  runtime pool warm, such as the bundled benchmark HTTP server or another
  resident worker model. One-shot CLI runs include Runtime startup cost and are
  cold-start references, not steady-state per-request measurements.

### Adapter Guide

| Situation | Recommended adapter |
|---|---|
| Swoole HTTP server is acceptable and high throughput is needed | Swoole adapter |
| A resident process can keep ext-parallel runtimes warm | ext-parallel adapter |
| Extension support is unavailable or portability is the priority | Sync adapter |

### Cases with Little or No Gain

- The downstream database or API cannot absorb the added concurrency because
  of pool limits, saturation, or rate limits.
- Each embedded resource is already extremely fast; this demo does not measure
  the boundary where fixed runtime overhead dominates.
- Embedded resources have real ordering dependencies or share mutable
  request-local state.
- One-shot CLI and cron-style jobs can still use the adapters, but they should
  be read as cold-start behavior. In this demo the ext-parallel one-shot CLI
  run is about 64 ms slower than Sync because Runtime startup is included.

## Cold One-Shot CLI Results

These numbers are reference values for a single CLI invocation of the dashboard
resource with 8 embeds. They include startup work such as DI lookup and, for
ext-parallel, Runtime spawn.

| Profile | Runtime | Time | vs profile Sync |
|---|---|---:|---:|
| ext-parallel CLI | Sync | 137.97 ms | baseline |
| ext-parallel CLI | ext-parallel | 201.87 ms | +63.90 ms (0.68x) |
| Swoole CLI | Sync | 140.45 ms | baseline |
| Swoole CLI | Swoole | 47.64 ms | -92.81 ms (2.95x) |

The ext-parallel cold result should not be used to judge steady-state
per-request performance. It is useful for understanding one-shot behavior and
for keeping the benchmark honest about startup cost.

## Steady-State HTTP Results

The following numbers were measured locally with Docker Compose on
2026-05-13. Each row is the median of three 30-second `wrk` runs against
`/dashboard?user_id=1`. The `c=1,t=2` combination is omitted because `wrk`
requires the number of connections to be greater than or equal to the number
of threads.

The CPU, memory, and MySQL columns are a single mid-run sample for orientation;
they are not peak measurements. Socket timeouts are summed across the three
runs.

| Runtime | Config | Connections | Threads | Runs | Median req/s | Median latency | Socket timeouts | Sample CPU | Sample memory | MySQL threads |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---|---:|
| ext-parallel | workers=1,pool=8 | 1 | 1 | 3 | 21.79 | 45.82 ms | 0 | 5.37% | 96.74MiB / 7.652GiB | 9 |
| ext-parallel | workers=4,pool=8 | 4 | 1 | 3 | 83.19 | 47.99 ms | 0 | 15.22% | 284MiB / 7.652GiB | 33 |
| ext-parallel | workers=4,pool=8 | 4 | 2 | 3 | 83.38 | 47.90 ms | 0 | 19.43% | 284.5MiB / 7.652GiB | 33 |
| ext-parallel | workers=16,pool=8 | 16 | 1 | 3 | 318.42 | 50.02 ms | 0 | 32.01% | 1.001GiB / 7.652GiB | 129 |
| ext-parallel | workers=16,pool=8 | 16 | 2 | 3 | 317.58 | 50.26 ms | 0 | 109.19% | 1.001GiB / 7.652GiB | 129 |
| Swoole | pdo_pool=64 | 1 | 1 | 3 | 54.10 | 18.48 ms | 0 | 39.80% | 69.89MiB / 7.652GiB | 65 |
| Swoole | pdo_pool=64 | 4 | 1 | 3 | 158.73 | 25.18 ms | 0 | 85.40% | 56.07MiB / 7.652GiB | 65 |
| Swoole | pdo_pool=64 | 4 | 2 | 3 | 157.75 | 25.33 ms | 0 | 89.57% | 59.73MiB / 7.652GiB | 65 |
| Swoole | pdo_pool=64 | 16 | 1 | 3 | 279.66 | 57.16 ms | 0 | 162.77% | 71.85MiB / 7.652GiB | 65 |
| Swoole | pdo_pool=64 | 16 | 2 | 3 | 279.15 | 57.25 ms | 0 | 158.54% | 64.89MiB / 7.652GiB | 65 |
| Swoole | pdo_pool=8 | 1 | 1 | 3 | 54.27 | 18.41 ms | 0 | 40.72% | 54.27MiB / 7.652GiB | 9 |
| Swoole | pdo_pool=8 | 4 | 1 | 3 | 59.62 | 67.02 ms | 0 | 41.04% | 54.14MiB / 7.652GiB | 9 |
| Swoole | pdo_pool=8 | 4 | 2 | 3 | 59.32 | 67.32 ms | 0 | 41.64% | 53.79MiB / 7.652GiB | 9 |
| Swoole | pdo_pool=8 | 16 | 1 | 3 | 60.07 | 265.27 ms | 0 | 40.08% | 347.1MiB / 7.652GiB | 9 |
| Swoole | pdo_pool=8 | 16 | 2 | 3 | 59.90 | 265.73 ms | 0 | 39.25% | 61.7MiB / 7.652GiB | 9 |

### Interpretation

- The throughput and latency numbers are internally consistent. For example,
  ext-parallel at 16 connections reports about 50 ms latency, and
  `16 / 0.050s` is about 320 req/s. Swoole with `PDO_POOL_SIZE=64` at 16
  connections reports about 57 ms latency, and `16 / 0.057s` is about
  280 req/s.
- Read this as a comparison of demo server configurations, not as a universal
  runtime ranking. The ext-parallel benchmark uses multiple HTTP worker
  processes, each with its own runtime pool. Swoole uses one HTTP server with a
  coroutine PDO pool.
- ext-parallel scales with the number of benchmark HTTP workers. A single
  worker serves one dashboard request in about 46 ms. At 16 connections it
  reaches about 318 req/s with about 50 ms median latency and no socket
  timeouts. The cost is resource growth: this configuration creates 16 HTTP
  worker processes, each with an 8-runtime pool, and the sampled MySQL
  connection count is 129.
- Swoole with `PDO_POOL_SIZE=64` is much lighter in memory and has better
  single-connection latency. It reaches about 279 req/s at 16 connections,
  with a sampled MySQL connection count of 65. The high connection count is
  intentional in this profile because the demo Swoole server pre-fills the
  configured PDO pool before serving requests.
- Swoole with `PDO_POOL_SIZE=8` demonstrates pool backpressure. It keeps MySQL
  connections low, but throughput stays around 60 req/s and latency grows as
  concurrent HTTP requests queue behind the smaller pool.
- Increasing `WRK_THREADS` from 1 to 2 does not materially change the result
  for this matrix. Connection count and runtime/pool sizing dominate.
- ext-parallel warms the shared DI/cache before worker startup, then warms each
  HTTP worker before `wrk`; the runtime pool startup cost is therefore outside
  the measured 30-second run.
- The CPU and memory samples are useful for orientation only. They are single
  mid-run Docker samples, not peak or average resource measurements, so they
  should not be used as the primary basis for resource sizing.

## Benchmark Scenarios

Each benchmark tests the dashboard resource, which embeds 8 independent SQL
resources. Each embedded resource simulates database latency.

### Scenario Configuration

| Scenario | Embedded Resources | Delay per Resource |
|----------|-------------------|-------------------|
| Dashboard | 8 | 10ms |

## Expected Results

### Theoretical Performance

| Scenario | Sync Time | Parallel Time | Speedup |
|----------|-----------|---------------|---------|
| Dashboard (8 embeds) | ~80ms + SQL/render overhead | ~10ms + SQL/render/thread overhead | up to 8x |

In synchronous mode, total time approaches the sum of all embedded resource
costs. With parallel execution, steady-state HTTP time approaches the maximum
single embedded resource cost plus runtime overhead. Cold one-shot CLI results
also include startup costs and should be read as reference values, not
steady-state per-request latency.

### Real-World Factors

Actual speedup varies based on:

- **Runtime startup**: cold one-shot CLI includes DI and runtime setup work
- **Database connection pool**: pool size, queueing, and connection creation
- **CPU cores**: ext-parallel benefits from multiple cores
- **I/O vs CPU bound**: Parallel execution excels for I/O-bound operations

## Comparison: ext-parallel vs ext-swoole

| Feature | ext-parallel | ext-swoole |
|---------|-------------|------------|
| Execution Model | Thread pool | Coroutines |
| Memory Isolation | Isolated per thread | Shared (requires pooling) |
| PDO Handling | Each thread gets own PDO | Must use connection pool |
| Best For | Resident worker pools with isolated runtimes | Long-running coroutine HTTP server |
| Use Case | warmed worker process or benchmark harness | Swoole HTTP Server |

### Module Selection Guide

#### Use the `bin/async.php` entrypoint (ext-parallel) when:
- Running a CLI entrypoint that should resolve embeds through ext-parallel
- Each request requires isolated memory
- No special PDO connection management needed
- For steady-state performance evaluation, use a resident process that keeps
  the `parallel\Runtime` pool warm

#### Use AsyncSwooleModule when:
- Running Swoole HTTP Server
- Need coroutine-based concurrency
- Must use `PdoPoolModule` for database connections

## Sample Output

```text
BEAR.Async Parallel Benchmark
==============================
8 embedded SQL resources, cold one-shot reference
This includes DI lookup and one-time ext-parallel Runtime spawn cost.
Use composer steady-state-parallel for HTTP steady-state measurements.

Sync execution (prod-hal-app)...
  Elapsed: 120.00 ms

Parallel execution (prod-hal-app + ParallelRuntimeModule override)...
  Elapsed: 350.00 ms (includes one-time thread pool spawn cost)

Note: this one-shot CLI run includes ext-parallel Runtime spawn.
      It is a cold-start reference, not a steady-state per-request benchmark.
```
