---
layout: default
title: BEAR.Async Documentation
---

# BEAR.Async

Async/parallel resource execution library for BEAR.Sunday.

## Overview

BEAR.Async enables transparent parallel execution of BEAR.Sunday's `#[Embed]` resources.

Traditional async programming requires rewriting code with special syntax like async/await, Promise, or yield. Developers must explicitly manage concurrency and learn new patterns. BEAR.Async takes a different approach: it preserves your resource code and lets you choose an async execution mode at the application boundary. Your existing `#[Embed]` attributes work as-is and embedded resources automatically execute in parallel.

```php
#[Embed(rel: 'profile', src: 'app://self/user/profile?id={user_id}')]
#[Embed(rel: 'posts', src: 'app://self/user/posts?user_id={user_id}')]
#[Embed(rel: 'notifications', src: 'app://self/notifications?user_id={user_id}')]
public function onGet(int $user_id): static
```

With an async execution mode selected, these 3 embeds execute **in parallel** instead of sequentially. If each resource takes 50ms to fetch, synchronous execution takes 150ms total, while parallel execution completes in approximately 50ms—a 3x speedup with zero code changes.

## Execution Modes

### Parallel execution (ext-parallel)

For PHP-FPM/Apache environments using an ext-parallel thread pool. Add a
`bin/async.php` entrypoint that hands off to the library bootstrap, which
overlays the ext-parallel runtime on the normal AppModule:

```text
bin/async.php → vendor/bear/async/bootstrap.php → AppModule + runtime overlay
```

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

Do not install the parallel runtime in `AppModule` directly — the bootstrap
is the only supported install path. The same `AppModule` works under
`bin/app.php` (sync) and `bin/async.php` (parallel) unchanged.

### Swoole execution (ext-swoole)

For Swoole HTTP Server environments using coroutines.

ext-parallel uses worker runtimes, so it is selected by a separate entrypoint.
ext-swoole runs inside one server process, so it is installed as an application
module.

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

PHP 8.2+ for the library itself. Each execution mode adds its own runtime
requirement:

| Mode | Requires | Application change |
|---|---|---|
| ext-parallel | ZTS PHP + ext-parallel | add `bin/async.php` |
| ext-swoole | ext-swoole | install `AsyncSwooleModule`, use `bin/swoole.php` |
