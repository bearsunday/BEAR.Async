---
layout: docs-en
title: Benchmark Results
category: Reference
permalink: /manuals/1.0/en/benchmark-results.html
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

## Troubleshooting

### ext-parallel not available

```bash
# Check if extension is loaded
docker-compose run --rm php php -m | grep parallel
```

### ext-swoole not available

```bash
# Check if extension is loaded
docker-compose run --rm php php -m | grep swoole
```

### Database connection errors

Ensure MySQL is running and healthy:

```bash
docker-compose up -d mysql
docker-compose exec mysql mysqladmin ping -h localhost -uroot
```

### Connection pool exhaustion (Swoole)

If you see pool timeout errors, increase pool size:

```php
$this->install(new PdoPoolModule($dsn, $user, $pass, 128)); // Increase from 64
```
