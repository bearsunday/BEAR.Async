# BEAR.AsyncDemo

Demo application for BEAR.Async parallel execution with ext-parallel and ext-swoole.

## Overview

This demo demonstrates parallel execution of `#[Embed]` resources using SQL queries.

### Dashboard Resource

The `Dashboard` resource embeds 8 independent SQL-based resources:

```php
#[Embed(rel: 'profile', src: 'app://self/user/profile?id={user_id}')]
#[Embed(rel: 'notifications', src: 'app://self/user/notifications?user_id={user_id}')]
#[Embed(rel: 'recent_posts', src: 'app://self/posts/recent')]
#[Embed(rel: 'popular_posts', src: 'app://self/posts/popular')]
#[Embed(rel: 'stats', src: 'app://self/stats')]
#[Embed(rel: 'categories', src: 'app://self/categories')]
#[Embed(rel: 'tags_cloud', src: 'app://self/tags/cloud')]
#[Embed(rel: 'activity', src: 'app://self/activity/recent?user_id={user_id}')]
public function onGet(int $user_id = 1): static
```

### Expected Results

| Mode | Execution | Expected Speedup |
|------|-----------|------------------|
| Sync | Sequential (8 queries one by one) | 1.0x (baseline) |
| ext-parallel | Thread pool (8 queries in parallel) | 2-4x |
| ext-swoole | Coroutines (8 queries in parallel) | 2-4x |

## Installation

```bash
composer install
php bin/setup.php
```

## Benchmarks

### ext-parallel (Thread Pool)

```bash
composer parallel-benchmark
```

Requires: PHP with ext-parallel (ZTS build)

### ext-swoole (Coroutines)

```bash
composer swoole-benchmark
```

Requires: PHP with ext-swoole

## Contexts

| Context | Module | Description |
|---------|--------|-------------|
| `prod-hal-app` | AppModule | Sync execution (baseline) |
| `prod-parallel-hal-app` | ParallelModule | ext-parallel thread pool |
| `prod-swoole-hal-app` | SwooleModule | ext-swoole coroutines |

## Docker (MySQL)

For MySQL benchmarks with realistic I/O latency:

```bash
# Start MySQL
docker compose up -d

# Wait for MySQL to be ready
docker compose exec mysql mysqladmin ping -h localhost --wait

# Create env.json for MySQL
cat > env.json << 'EOF'
{
    "DB_DSN": "mysql:host=127.0.0.1;dbname=demo",
    "DB_USER": "demo",
    "DB_PASS": "demo"
}
EOF

# Run benchmarks
composer parallel-benchmark
composer swoole-benchmark
```

## Commands

```bash
composer setup              # Initialize database
composer parallel-benchmark # Run ext-parallel benchmark
composer swoole-benchmark   # Run ext-swoole benchmark
composer xprofile           # Profile dashboard (sync) with Xdebug
composer xprofile-parallel  # Profile dashboard (parallel) with Xdebug
composer xprofile-swoole    # Profile dashboard (swoole) with Xdebug
composer test               # Run unit tests
composer tests              # Run all quality checks
composer cs-fix             # Fix coding standards
```

Note: Use `APP_CONTEXT` environment variable to change context:

```bash
APP_CONTEXT=prod-parallel-hal-app php bin/app.php get app://self/dashboard
```

## Links

- [BEAR.Sunday](http://bearsunday.github.io/)
- [BEAR.Async](https://github.com/bearsunday/BEAR.Async)
