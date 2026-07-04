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
│                        Main PHP Process                         │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐       │
│  │   Request    │───▶│  Dashboard   │───▶│ AsyncHal     │       │
│  │              │    │  Resource    │    │ Renderer     │       │
│  └──────────────┘    └──────────────┘    └──────────────┘       │
│                             │                    │              │
│                             ▼                    ▼              │
│                      ┌─────────────┐     ┌─────────────┐        │
│                      │ AsyncEmbed  │     │ Pending     │        │
│                      │ Interceptor │────▶│ Requests    │        │
│                      └─────────────┘     └─────────────┘        │
│                             │                    │              │
│                             ▼                    ▼              │
│                      ┌─────────────┐     ┌─────────────┐        │
│                      │AsyncRequest │     │ Parallel    │        │
│                      │DeferredReq. │────▶│ Async       │        │
│                      └─────────────┘     └─────────────┘        │
│                                                 │               │
└─────────────────────────────────────────────────│───────────────┘
                                                  │
                    ┌─────────────────────────────┼─────────────────────────────┐
                    │                    Thread Pool                            │
                    │                                                           │
                    │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
                    │  │ Runtime 1│  │ Runtime 2│  │ Runtime 3│  │ Runtime 4│   │
                    │  │[Resource]│  │[Resource]│  │[Resource]│  │[Resource]│   │
                    │  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
                    │       │             │             │             │         │
                    │       ▼             ▼             ▼             ▼         │
                    │   ┌───────┐     ┌───────┐     ┌───────┐     ┌───────┐     │
                    │   │ MySQL │     │ MySQL │     │ MySQL │     │ MySQL │     │
                    │   │ Query │     │ Query │     │ Query │     │ Query │     │
                    │   └───────┘     └───────┘     └───────┘     └───────┘     │
                    │                                                           │
                    └───────────────────────────────────────────────────────────┘
```

### Key Components

| Component | Responsibility |
|-----------|----------------|
| AsyncEmbedInterceptor | Replaces `EmbedInterceptorInterface`; wraps each `#[Embed]` in an `AsyncRequest` |
| AsyncRequest / DeferredRequest | Defers resource construction/invocation until the batch is dispatched |
| PendingRequests | Holds pending embed requests (singleton per request cycle; coroutine-local under Swoole) and dispatches the batch on first render (そうめん流し方式) |
| AsyncLinkCrawler | Replaces `LinkCrawlerInterface`; parallelizes `linkCrawl()` graphs level by level |
| ParallelAsync | Manages ext-parallel thread pool |
| Runtime | Individual worker thread with bootstrapped ResourceInterface |

Failures in any single task do not abort the batch: all sibling
tasks/coroutines are allowed to finish, and only the first exception
encountered is rethrown afterward (see "Failure Semantics" below).

### Failure Semantics

Both `ParallelAsync` and `SwooleAsync` apply the same rule: a `Throwable`
raised while executing one task/request never aborts its siblings, and
never crashes the Swoole worker process. Every dispatched task is always
allowed to run to completion — successful ones still populate their result
— and only after every task has finished is the first exception encountered
(in task/request iteration order) rethrown to the caller. This turns a
single failing embed into a 500 for that one request instead of an outage
for every in-flight request sharing the same worker.

There is deliberately no silent fallback between adapters: if the required
extension (`ext-parallel` or `ext-swoole`) is not loaded, `ParallelModule`
or `AsyncSwooleModule` throws `ExtensionNotLoadedException` at
`configure()` time rather than degrading quietly to `SyncAsync`.
`AsyncInterface` has no `isAvailable()` check to opt into that fallback —
extension availability is a hard requirement of the module you chose to
install.

## Thread Pool Lifecycle

`ParallelAsync` is bound `SINGLETON` and keeps its `parallel\Runtime` pool in
an instance property (`$pool`), so the pool's lifetime is exactly the
lifetime of the PHP process that owns that singleton — not "once globally".
This distinction matters for where the process boundary actually sits:

### Classic PHP-FPM / Apache: pool rebuilt every request

A classic PHP-FPM or `php-cgi` worker does not persist application-level
objects between requests — each request gets a fresh process (or a fresh
bootstrap of the DI container within a reused OS process, depending on the
SAPI/opcache configuration), so `ParallelAsync` and its pool are constructed
from scratch on every request that uses embeds:

```text
Every PHP-FPM Request
       │
       ▼
AppModule + ParallelRuntimeModule override (fresh container)
       │
       ▼
First embed/crawl needing AsyncInterface
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
Pool used for this request only, then destroyed with the process/container
```

Under this model there is no amortization: every request pays full thread
spawn + autoload + DI container build cost for every worker in the pool.
This is why the README and benchmark results recommend a **resident worker
process** for steady-state ext-parallel use.

### Resident worker process: pool reused across requests

A resident process that keeps the same `ParallelAsync` singleton alive
across many requests — such as `demo/bin/parallel-server.php` — pays the
pool-initialization cost once and reuses the warm pool for all subsequent
requests:

```text
Resident Worker Process Start
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
Pool Ready (reused for all subsequent requests on this process)
```

`ParallelAsync` also warms exactly one worker synchronously before
dispatching any task, serializing the (expensive) cold DI container build so
the remaining pool threads hit a warm `WorkerResourceCache` instead of all
compiling the same application concurrently.

### Request Processing (Every Request)

```text
Request with 10 Embeds
       │
       ▼
AsyncEmbedInterceptor
       │
       ├── Embed 1 ──▶ AsyncRequest ──┐
       ├── Embed 2 ──▶ AsyncRequest ──┤
       ├── ...                        ├──▶ PendingRequests
       └── Embed 10 ─▶ AsyncRequest ──┘
       │
       ▼
PendingRequests::executePending()
       │
       ▼
ParallelAsync::execute()
       │
       ├── Runtime 1 ◀── Task 1, 5, 9
       ├── Runtime 2 ◀── Task 2, 6, 10
       ├── Runtime 3 ◀── Task 3, 7
       └── Runtime 4 ◀── Task 4, 8
       │
       ▼
Join every dispatched Future, then resolve results
(first Throwable, if any, is rethrown only after all futures are joined)
```

## Cost Analysis

### Bootstrap Cost

| Phase | Cost | Frequency |
|-------|------|-----------|
| Pool initialization | scales with poolSize | Once per resident process; every request under classic PHP-FPM (see above) |
| Thread communication | per-task overhead | Per request |
| Task execution | I/O time | Per request |

For measured cold one-shot CLI vs. steady-state HTTP numbers, see
[Benchmark Results](benchmark-results.md) — cold one-shot CLI runs include
full Runtime startup cost, while steady-state HTTP numbers assume a resident
process that keeps the runtime pool warm.

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

The predictions in this section are illustrative, theoretical projections
based on the cost model above — they are not measurements. They assume a
warm, resident runtime pool (see "Resident worker process" above); under
classic PHP-FPM/Apache without a resident worker, add full pool
initialization cost to every request instead of amortizing it away. For
actual measured numbers, see [Benchmark Results](benchmark-results.md).

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

Pool size is passed as the optional 6th argument to the library bootstrap
from `bin/async.php`:

```php
exit((require $bootstrap)(
    $context,
    'MyVendor\MyApp',
    dirname(__DIR__),
    $GLOBALS,
    $_SERVER,
    null,  // Auto-detect CPU cores (recommended)
));
```

**Guidelines:**
- Default (null): Uses CPU core count - good for most cases
- Explicit value: Set to max expected embed count if known
- Maximum useful: Equal to maximum concurrent embeds
- As a starting point for sizing, `PARALLEL_POOL_SIZE >= embed_count` lets
  one request's embeds all run in a single round per resident worker
  process; see [Benchmark Results](benchmark-results.md#when-to-choose-parallel)
  for how pool size, worker-process count, and measured throughput interact
- This pool sizing only pays off under a resident worker process — under
  classic PHP-FPM the pool (and its DI/autoload cost) is rebuilt every
  request regardless of size

### Instance Type Recommendations

| Traffic Level | Recommended Instance | Pool Size | Notes |
|---------------|---------------------|-----------|-------|
| Development | t3.micro | 2 | Cost-effective testing |
| Small (< 1M PV) | t3.medium | 2 | Burstable, low cost |
| Medium (1-10M PV) | c5.xlarge | 4 | Compute optimized |
| Large (10-100M PV) | c5.2xlarge | 8 | Good balance |
| Very Large (> 100M PV) | c5.4xlarge | 16 | High throughput |

### Connection Pool Hardening (Swoole)

`PdoPoolModule`/`RedisPoolModule` size their pools independently of the
ext-parallel worker pool above. Guidance:

- Size the pool to roughly `embed_count * request_concurrency` so one
  dashboard-style request does not starve concurrent requests of
  connections; see [Benchmark Results](benchmark-results.md#when-to-choose-parallel)
  for measured throughput and MySQL connection counts at different
  `PDO_POOL_SIZE` values.
- Borrowing blocks for at most `borrowTimeout` seconds (default 5.0,
  configurable per module) before throwing `PoolTimeoutException`, so pool
  exhaustion fails fast instead of hanging the request indefinitely.
- Every PDO checkout is pinged (`SELECT 1`) before being handed out; a dead
  connection (e.g. after a MySQL restart or `wait_timeout`) is discarded and
  retried once, so the pool self-heals instead of poisoning every borrower
  with a connection that will fail on first use. If no live connection can
  be found, `StalePooledConnectionException` is thrown.
- Redis connections are cached per coroutine the same way PDO connections
  are, avoiding redundant pool checkouts within a single coroutine.

**WARNING**: injecting `ExtendedPdoInterface` (or `PDO`/`Redis`) into a
`SINGLETON`-scoped class captures one coroutine's borrowed connection for
the life of the process, defeating the pool and corrupting concurrent
coroutines that share it. Keep DB-using dependencies prototype-scoped
(the default Ray.Di scope), or inject a provider and call `get()` per use
instead of caching the connection on a singleton.

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
| Thread communication | small per-request overhead | Acceptable when I/O time per embed dominates it |
| Memory per worker | grows with pool size and worker-process count | Size instance appropriately; see [Benchmark Results](benchmark-results.md) sample memory columns |
| Runtime pool bootstrap (thread spawn + autoload + DI build) | amortized only under a resident worker process | Run a resident process (e.g. `demo/bin/parallel-server.php`); under classic PHP-FPM this cost is paid on every request, not amortized |

## Conclusion

BEAR.Async's parallel execution can meaningfully speed up I/O-bound embed
operations, but the win is conditional on the runtime hosting model:

- Under a **resident worker process** that keeps the `parallel\Runtime` pool
  warm, only per-request thread communication and I/O time remain in the
  critical path — see [Benchmark Results](benchmark-results.md) for measured
  steady-state HTTP numbers.
- Under **classic PHP-FPM/Apache** without a resident process, the pool
  (thread spawn, autoload, DI container build) is rebuilt on every request,
  so cold one-shot CLI numbers in [Benchmark Results](benchmark-results.md)
  are the relevant reference, not the amortized model above.
- **Cost-effectiveness** depends on this same distinction: pool-size
  (auto-detected from CPU cores by default) and instance-sizing decisions
  should be validated against measured results for your actual hosting model,
  not the theoretical projections in this document alone.
