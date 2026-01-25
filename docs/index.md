---
layout: default
title: BEAR.Async Documentation
---

# BEAR.Async

Async/parallel resource execution library for BEAR.Sunday.

## Overview

BEAR.Async enables transparent parallel execution of BEAR.Sunday's `#[Embed]` resources by replacing the `LinkerInterface` implementation. It provides significant performance improvements for I/O-bound operations.

## Modules

### AsyncParallelModule

For PHP-FPM/Apache environments using ext-parallel thread pool.

```php
$this->install(new AsyncParallelModule(
    namespace: 'MyVendor\MyApp',
    context: 'prod-app',
    appDir: dirname(__DIR__),
));
```

### AsyncSwooleModule

For Swoole HTTP Server environments using coroutines.

```php
$this->install(new AsyncSwooleModule());
$this->install(new PdoPoolEnvModule(
    'PDO_DSN',
    'PDO_USER',
    'PDO_PASSWORD',
));
```

## Documentation

- [Parallel Execution Architecture](parallel-execution-architecture.html) - Architecture overview, AWS instance recommendations, and cost analysis
- [Benchmark Results](benchmark-results.html) - Performance benchmarks comparing sync vs parallel execution

## Requirements

- PHP 8.2+
- bear/resource ^1.17
- ext-parallel or ext-swoole
