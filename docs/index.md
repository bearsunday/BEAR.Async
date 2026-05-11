---
layout: default
title: BEAR.Async Documentation
---

# BEAR.Async

Async/parallel resource execution library for BEAR.Sunday.

## Overview

BEAR.Async enables transparent parallel execution of BEAR.Sunday's `#[Embed]` resources.

Traditional async programming requires rewriting code with special syntax like async/await, Promise, or yield. Developers must explicitly manage concurrency and learn new patterns. BEAR.Async takes a different approach: your existing `#[Embed]` attributes work as-is. Just install a module, and embedded resources automatically execute in parallel. No syntax changes, no rewriting, no learning curve.

```php
#[Embed(rel: 'profile', src: 'app://self/user/profile?id={user_id}')]
#[Embed(rel: 'posts', src: 'app://self/user/posts?user_id={user_id}')]
#[Embed(rel: 'notifications', src: 'app://self/notifications?user_id={user_id}')]
public function onGet(int $user_id): static
```

With BEAR.Async module installed, these 3 embeds execute **in parallel** instead of sequentially. If each resource takes 50ms to fetch, synchronous execution takes 150ms total, while parallel execution completes in approximately 50ms—a 3x speedup with zero code changes.

## Modules

### Parallel execution (ext-parallel)

For PHP-FPM/Apache environments using an ext-parallel thread pool. `AppModule`
stays ignorant of execution form — add a dedicated `bin/async.php` entrypoint
that loads the library bootstrap:

```php
<?php // bin/async.php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

$bootstrap = dirname(__DIR__) . '/vendor/bear/async/bootstrap.php';
$context = getenv('APP_CONTEXT') ?: 'hal-api-app';

exit((require $bootstrap)(
    $context,
    'MyVendor\MyApp',
    dirname(__DIR__),
    $GLOBALS,
    $_SERVER,
));
```

The library bootstrap overlays the ext-parallel runtime on your `AppModule`.
Applications should not install the internal runtime modules directly.

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

- ext-parallel or ext-swoole
