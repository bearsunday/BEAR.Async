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

- **Thread/Coroutine overhead**: Initial setup cost (~10-20ms)
- **Database connection pool**: Connection acquisition time
- **CPU cores**: ext-parallel benefits from multiple cores
- **I/O vs CPU bound**: Parallel execution excels for I/O-bound operations

## Comparison: ext-parallel vs ext-swoole

| Feature | ext-parallel | ext-swoole |
|---------|-------------|------------|
| Execution Model | Thread pool | Coroutines |
| Memory Isolation | Isolated per thread | Shared (requires pooling) |
| PDO Handling | Each thread gets own PDO | Must use connection pool |
| Best For | CPU-bound + I/O mixed | Pure I/O-bound operations |
| Use Case | PHP-FPM/Apache | Swoole HTTP Server |

### Module Selection Guide

#### Use the `bin/async.php` entrypoint (ext-parallel) when:
- Running under PHP-FPM or Apache
- Each request requires isolated memory
- No special PDO connection management needed

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
