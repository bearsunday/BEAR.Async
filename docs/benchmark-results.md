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
# Build Docker image with both extensions
docker-compose build php

# Run ext-parallel benchmark
docker-compose run --rm php php demo/bin/parallel-benchmark.php 5

# Run ext-swoole benchmark
docker-compose run --rm php php demo/bin/swoole-benchmark.php 5
```

## Simulation Methodology

Each embedded resource uses `SlowQueryInterceptor` to add artificial delay, simulating realistic I/O latency. This approach provides reproducible benchmarks that reflect real-world conditions:

| Operation | Typical Latency |
|-----------|-----------------|
| Simple SELECT | 1-5ms |
| JOIN/Aggregation | 10-50ms |
| Network latency (RDS) | 1-10ms |
| Template processing | 1-10ms |

The default 10ms delay represents a conservative, realistic per-resource overhead combining SQL execution, network latency, and processing time.

You can verify these results in our [CI benchmark workflow](https://github.com/bearsunday/BEAR.Async/actions/workflows/async-benchmark.yml), which runs on every push and pull request.

## Benchmark Scenarios

Each benchmark tests a dashboard resource that embeds multiple slow resources. Each embedded resource simulates database latency with configurable delays.

### Scenario Configuration

| Scenario | Embedded Resources | Delay per Resource |
|----------|-------------------|-------------------|
| Small    | 3                 | 50ms              |
| Medium   | 5                 | 50ms              |
| Large    | 11                | 50ms              |

## Expected Results

### Theoretical Performance

| Scenario | Sync Time | Parallel Time | Speedup |
|----------|-----------|---------------|---------|
| Small (3 embeds)  | 150ms | ~50ms | 3x |
| Medium (5 embeds) | 250ms | ~50ms | 5x |
| Large (11 embeds) | 550ms | ~50ms | 11x |

In synchronous mode, total time equals the sum of all delays. With parallel execution, total time approaches the maximum single delay (plus overhead).

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

#### Use AsyncParallelModule when:
- Running under PHP-FPM or Apache
- Each request requires isolated memory
- No special PDO connection management needed

#### Use AsyncSwooleModule when:
- Running Swoole HTTP Server
- Need coroutine-based concurrency
- Must use `PdoPoolModule` for database connections

## Sample Output

```text
=== BEAR.Async Parallel Benchmark ===
Iterations: 5
Delay per resource: 50ms

--- Small Dashboard (3 embeds) ---
Sync:     151.23ms (avg)
Parallel:  52.45ms (avg)
Speedup:   2.88x

--- Medium Dashboard (5 embeds) ---
Sync:     252.18ms (avg)
Parallel:  54.32ms (avg)
Speedup:   4.64x

--- Large Dashboard (11 embeds) ---
Sync:     553.67ms (avg)
Parallel:  58.91ms (avg)
Speedup:   9.40x
```
