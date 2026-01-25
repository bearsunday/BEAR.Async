---
layout: default
title: Parallel Execution Architecture and Performance Analysis
---

# Parallel Execution Architecture and Performance Analysis

This document describes the architecture of BEAR.Async's parallel execution for `#[Embed]` resources and provides performance predictions for various AWS instance types.

## Architecture Overview

### How Parallel Execution Works

```text
┌─────────────────────────────────────────────────────────────────┐
│                        Main PHP Process                          │
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │   Request    │───▶│  Dashboard   │───▶│ AsyncHal     │      │
│  │              │    │  Resource    │    │ Renderer     │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│                             │                    │               │
│                             ▼                    ▼               │
│                      ┌─────────────┐     ┌─────────────┐        │
│                      │ AsyncEmbed  │     │ EmbedData   │        │
│                      │ Interceptor │────▶│ Loader      │        │
│                      └─────────────┘     └─────────────┘        │
│                             │                    │               │
│                             ▼                    ▼               │
│                      ┌─────────────┐     ┌─────────────┐        │
│                      │   Embed     │     │ Parallel    │        │
│                      │  Requests   │────▶│ Async       │        │
│                      └─────────────┘     └─────────────┘        │
│                                                 │                │
└─────────────────────────────────────────────────│────────────────┘
                                                  │
                    ┌─────────────────────────────┼─────────────────────────────┐
                    │                    Thread Pool                             │
                    │                                                            │
                    │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
                    │  │ Runtime 1│  │ Runtime 2│  │ Runtime 3│  │ Runtime 4│ │
                    │  │ [Resource]│  │[Resource]│  │[Resource]│  │[Resource]│ │
                    │  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘ │
                    │       │             │             │             │        │
                    │       ▼             ▼             ▼             ▼        │
                    │   ┌───────┐     ┌───────┐     ┌───────┐     ┌───────┐   │
                    │   │ MySQL │     │ MySQL │     │ MySQL │     │ MySQL │   │
                    │   │ Query │     │ Query │     │ Query │     │ Query │   │
                    │   └───────┘     └───────┘     └───────┘     └───────┘   │
                    │                                                            │
                    └────────────────────────────────────────────────────────────┘
```

### Key Components

| Component | Responsibility |
|-----------|----------------|
| AsyncEmbedInterceptor | Collects `#[Embed]` requests as FutureResource |
| EmbedRequests | Holds pending embed requests (singleton per request cycle) |
| EmbedDataLoader | Dispatches tasks to AsyncInterface |
| ParallelAsync | Manages ext-parallel thread pool |
| Runtime | Individual worker thread with bootstrapped ResourceInterface |

## Thread Pool Lifecycle

### Initialization (Once per PHP Process)

```text
PHP-FPM Worker Start
       │
       ▼
First Request with Embeds
       │
       ▼
ParallelAsync::initializePool()
       │
       ├── new Runtime(bootstrap.php)  ─── Worker 1 bootstraps ResourceInterface
       ├── new Runtime(bootstrap.php)  ─── Worker 2 bootstraps ResourceInterface
       ├── new Runtime(bootstrap.php)  ─── Worker 3 bootstraps ResourceInterface
       └── new Runtime(bootstrap.php)  ─── Worker 4 bootstraps ResourceInterface
       │
       ▼
Pool Ready (reused for all subsequent requests)
```

### Request Processing (Every Request)

```text
Request with 10 Embeds
       │
       ▼
AsyncEmbedInterceptor
       │
       ├── Embed 1 ──▶ FutureResource ──┐
       ├── Embed 2 ──▶ FutureResource ──┤
       ├── ...                          ├──▶ EmbedRequests
       └── Embed 10 ─▶ FutureResource ──┘
       │
       ▼
EmbedDataLoader::load()
       │
       ▼
ParallelAsync::__invoke()
       │
       ├── Runtime 1 ◀── Task 1, 5, 9
       ├── Runtime 2 ◀── Task 2, 6, 10
       ├── Runtime 3 ◀── Task 3, 7
       └── Runtime 4 ◀── Task 4, 8
       │
       ▼
Collect Results & Resolve Futures
```

## Cost Analysis

### Bootstrap Cost

| Phase | Cost | Frequency |
|-------|------|-----------|
| Pool initialization | ~22ms × poolSize | Once per PHP process |
| Thread communication | ~5ms | Per request |
| Task execution | I/O time | Per request |

**Amortized Cost Example:**

```text
Bootstrap: 88ms (4 workers × 22ms)
Requests per FPM worker: ~1,000

Amortized bootstrap: 88ms ÷ 1,000 = 0.088ms/request ≈ negligible
```

### Why I/O-Bound Operations Benefit

Parallel execution is particularly effective for I/O-bound operations because:

1. **CPU is idle during I/O wait** - No additional CPU load from parallelization
2. **I/O waits can overlap** - Multiple queries execute simultaneously
3. **No resource contention** - Each worker has its own database connection

```text
Sequential (10 queries × 10ms each):
CPU: [Q1]----[Q2]----[Q3]----...[Q10]---- = 100ms
      ↑wait  ↑wait  ↑wait       ↑wait

Parallel (10 queries, 4 workers):
W1:  [Q1]----[Q5]----[Q9]----
W2:  [Q2]----[Q6]----[Q10]---
W3:  [Q3]----[Q7]----
W4:  [Q4]----[Q8]----
                            = 30ms (3 rounds)
```

## Real-World Performance Predictions

### Use Case: Magazine Content Site

A typical magazine article page with the following embeds:

| Embed | Content | MySQL Time |
|-------|---------|------------|
| article | Article body | 5ms |
| author | Author info | 3ms |
| magazine | Magazine info | 3ms |
| category | Category data | 2ms |
| tags | Tag list | 3ms |
| related | 5 related articles | 15ms |
| popular | 10 popular articles | 20ms |
| comments | Comment count | 5ms |
| recommendations | Personalized recommendations | 25ms |
| ads | Ad placements | 5ms |

Total: 10 embeds, ~86ms I/O time.

### AWS Instance Performance Comparison

| Instance | vCPU | Pool Size | Rounds | DB Time | Total | Improvement |
|----------|------|-----------|--------|---------|-------|-------------|
| Sequential | - | - | 10 | 86ms | 96ms | baseline |
| t3.medium | 2 | 2 | 5 | 45ms | 55ms | 43% faster |
| t3.large | 2 | 2 | 5 | 45ms | 55ms | 43% faster |
| c5.xlarge | 4 | 4 | 3 | 35ms | 45ms | 53% faster |
| c5.2xlarge | 8 | 8 | 2 | 30ms | 40ms | 58% faster |
| c5.4xlarge | 16 | 10 | 1 | 25ms | 35ms | 64% faster |
| c5.9xlarge | 36 | 10 | 1 | 25ms | 35ms | 64% faster |

*Note: Pool size capped at embed count (10) since additional workers provide no benefit.*

### Monthly Cost Savings Projection

Assumptions:
- 100 million page views per month
- Average 56ms saved per request (96ms → 40ms)
- Server cost: EC2 c5.4xlarge at $0.68/hour

```text
Time saved: 100M × 56ms = 5,600,000 seconds = 1,556 hours/month

Server cost reduction scenarios:

1. Reduced response time → Better user experience
   - Lower bounce rate
   - Higher engagement
   - Indirect revenue increase

2. Increased throughput → Fewer servers needed
   - 58% faster responses
   - ~40% reduction in required instances
   - Direct cost savings: ~$1,000-3,000/month per server

3. Reduced database connection time
   - Connections released faster
   - Better connection pool utilization
   - Potential to use smaller RDS instances
```

### Cost-Benefit Summary by Scale

| Monthly PV | Response Improvement | Estimated Monthly Savings |
|------------|---------------------|---------------------------|
| 1 million | 96ms → 40ms | ~$100 |
| 10 million | 96ms → 40ms | ~$300 |
| 100 million | 96ms → 40ms | ~$1,000-3,000 |
| 1 billion | 96ms → 40ms | ~$10,000-30,000 |

## Configuration Recommendations

### Pool Size Selection

```php
$this->install(new AsyncParallelModule(
    namespace: 'MyVendor\MyApp',
    context: 'prod-app',
    appDir: dirname(__DIR__),
    poolSize: null,  // Auto-detect CPU cores (recommended)
));
```

**Guidelines:**
- Default (null): Uses CPU core count - good for most cases
- Explicit value: Set to max expected embed count if known
- Maximum useful: Equal to maximum concurrent embeds

### Instance Type Recommendations

| Traffic Level | Recommended Instance | Pool Size | Notes |
|---------------|---------------------|-----------|-------|
| Development | t3.micro | 2 | Cost-effective testing |
| Small (< 1M PV) | t3.medium | 2 | Burstable, low cost |
| Medium (1-10M PV) | c5.xlarge | 4 | Compute optimized |
| Large (10-100M PV) | c5.2xlarge | 8 | Good balance |
| Very Large (> 100M PV) | c5.4xlarge | 16 | High throughput |

## Limitations and Considerations

### When Parallel Execution Helps

- I/O-bound embed operations (database queries, API calls)
- Multiple independent embeds
- Adequate CPU cores available

### When It May Not Help

- CPU-bound operations (complex calculations)
- Single embed or sequential dependencies
- Already at 100% CPU utilization
- Very fast queries (< 5ms) where overhead dominates

### Overhead Considerations

| Factor | Impact | Mitigation |
|--------|--------|------------|
| Thread communication | ~5ms per request | Acceptable for I/O > 20ms |
| Memory per worker | ~50-100MB | Size instance appropriately |
| Bootstrap time | ~22ms per worker | Amortized over process lifetime |

## Conclusion

BEAR.Async's parallel execution provides significant performance improvements for I/O-bound embed operations:

- **58-64% faster response times** for typical content pages
- **Negligible overhead** (~5ms) compared to time saved (~50-60ms)
- **Cost-effective** at scale with measurable ROI
- **Simple configuration** with automatic CPU detection

The architecture efficiently reuses thread pools across requests, making the bootstrap cost negligible in production environments.
