# BEAR.Async

Parallel execution of `#[Embed]` resources for BEAR.Sunday.

```php
#[Embed(rel: 'profile', src: 'app://self/user/profile?id={user_id}')]
#[Embed(rel: 'posts', src: 'app://self/user/posts?user_id={user_id}')]
#[Embed(rel: 'notifications', src: 'app://self/notifications?user_id={user_id}')]
public function onGet(int $user_id): static
```

These three embeds run in parallel. The resource code does not change; you pick
the execution mode at the application boundary.

## Installation

```bash
composer require bear/async
```

## Execution modes

| Server | Requires | Application change |
|---|---|---|
| PHP-FPM / Apache | ZTS PHP + ext-parallel | add `bin/async.php` (see [manual](https://bearsunday.github.io/manuals/1.0/en/async.html#parallel-execution-ext-parallel)) |
| Swoole HTTP Server | ext-swoole / ext-openswoole | add a `swoole` context module that installs `AsyncSwooleModule`; boot on the compiled injector (see [manual](https://bearsunday.github.io/manuals/1.0/en/async.html#swoole-execution-ext-swoole)) |

Without either extension the same code runs sequentially. A missing extension
fails at `configure()` with `ExtensionNotLoadedException`; there is no silent
fallback.

## Documentation

- [Manual: Parallel Resource Execution](https://bearsunday.github.io/manuals/1.0/en/async.html) — setup, constraints, pool sizing, failure semantics, how it works ([日本語](https://bearsunday.github.io/manuals/1.0/ja/async.html))
- [Demo guide](demo/README.md) — Docker-based demo for sequential, ext-parallel, and Swoole
- [Benchmark results](docs/benchmark-results.md) — cold CLI and steady-state HTTP measurements
- [Parallel execution architecture](https://bearsunday.github.io/BEAR.Async/parallel-execution-architecture.html)

## Demo

```bash
cd demo
docker compose up -d --wait parallel
docker compose exec parallel composer install
docker compose exec parallel composer app -- get 'app://self/dashboard?user_id=1'
docker compose exec parallel composer async -- get 'app://self/dashboard?user_id=1'
```

## Requirements

PHP 8.2+. Each execution mode adds its own extension requirement (table above).
