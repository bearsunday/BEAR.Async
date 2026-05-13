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

## Quick start (Docker)

The demo ships with a multistage Dockerfile that builds two purpose-built
images: one for ext-parallel (PHP 8.4 ZTS + ext-parallel) and one for
ext-swoole (PHP 8.4 ZTS + ext-swoole). The two extensions are kept in
separate images because their shutdown hooks conflict when loaded
together. MySQL ships in the same compose project and the app
services `depends_on` it. No host PHP or MySQL setup is required.

```bash
docker compose up -d --wait parallel                       # also brings MySQL up
docker compose exec parallel composer install              # installs deps and seeds MySQL
docker compose exec parallel composer app -- get 'app://self/dashboard?user_id=1'
```

`composer install` runs `composer setup` automatically, which drops
and re-creates the `demo` schema in MySQL and loads `sql/schema.sql` +
`sql/seed.sql` through PDO. The `parallel` service has `DB_DSN`,
`DB_USER`, and `DB_PASS` preset to point at the bundled MySQL.

## Running the demo

Each entrypoint serves the same resource graph; only the execution form
differs. Use `--` to forward the method and URI through composer.

### Sync (baseline)

The `parallel` service has plain PHP 8.4 ZTS available, so the sync
baseline runs there too.

```bash
docker compose exec parallel composer app -- get 'app://self/dashboard?user_id=1'
```

### ext-parallel (thread pool)

```bash
docker compose exec parallel composer async -- get 'app://self/dashboard?user_id=1'
```

### ext-swoole (coroutines)

```bash
docker compose up -d --wait swoole
docker compose exec swoole composer install            # first run only
docker compose exec swoole composer swoole
# In another terminal on the host (port 8080 is mapped):
curl 'http://127.0.0.1:8080/dashboard?user_id=1'
```

## Benchmarks

The CLI benchmark scripts are cold one-shot reference values. They include
startup work such as DI lookup and, for ext-parallel, one-time
`parallel\Runtime` spawn. Use the steady-state HTTP benchmarks when you want
per-request numbers after the server and worker pool are already running.

### Cold one-shot CLI

```bash
docker compose exec parallel composer parallel-benchmark
docker compose exec swoole composer swoole-benchmark
```

### Steady-state HTTP with wrk

The demo images include `wrk`. The benchmark command starts the matching
HTTP server, waits for it to become ready, runs `wrk`, then stops the server.
The default request is `/dashboard?user_id=1`; override it with
`BENCH_PATH` if needed. It sends one warmup request before `wrk` so
worker pools and connection pools are initialized outside the measured run.
The demo Swoole entrypoint also pre-fills `PDOPool` before accepting HTTP
requests so PDO connections are created serially at server startup rather than
concurrently during the first embedded-resource batch.
If Xdebug is active, Swoole entrypoints fail fast before starting coroutines;
use a PHP runtime without Xdebug or set `XDEBUG_MODE=off`.

```bash
docker compose exec parallel composer steady-state-parallel
docker compose exec swoole composer steady-state-swoole
```

The bundled ext-parallel HTTP server is a multi-process benchmark harness.
Each HTTP worker process keeps its own long-running `parallel\Runtime` pool,
so `WRK_CONNECTIONS` can exercise concurrent HTTP requests while keeping
thread-pool startup outside the measured run. Control the HTTP worker count
with `BENCH_PARALLEL_WORKERS` (default: `WRK_CONNECTIONS`).

Common tuning knobs:

```bash
docker compose exec -e WRK_DURATION=30s -e WRK_CONNECTIONS=1 parallel composer steady-state-parallel
docker compose exec -e WRK_DURATION=30s -e WRK_CONNECTIONS=16 -e WRK_THREADS=2 parallel composer steady-state-parallel
docker compose exec -e WRK_DURATION=30s -e WRK_CONNECTIONS=16 -e WRK_THREADS=2 swoole composer steady-state-swoole
docker compose exec -e BENCH_WARMUP_REQUESTS=3 parallel composer steady-state-parallel
```

To run the repeatable matrix used by the benchmark documentation, run this
from the host in the `demo/` directory:

```bash
composer steady-state-matrix
```

It runs three 30-second `wrk` measurements per valid connection/thread
combination, records median req/s and latency, and writes TSV plus Markdown
summaries under `build/`.

For Swoole, `PDO_POOL_SIZE` is still configurable, but the default pool is
enough for the demo benchmark. Embedded resources are instantiated inside the
batched coroutine execution path so the pool applies backpressure instead of
requiring one connection per potential embedded request up front. Set
`PDO_POOL_PREFILL=0` only when you specifically want to measure lazy pool
connection creation.

If you already have the server running, set `BENCH_USE_EXISTING=1` and the
script will only run `wrk`:

```bash
docker compose exec -e BENCH_USE_EXISTING=1 swoole composer steady-state-swoole
```

On a host machine with multiple PHP builds, set `SWOOLE_PHP` or `PARALLEL_PHP`
to the binary that has the matching extension loaded.

## Contexts and entrypoints

| Composer script | Entrypoint | Default context | Execution | Service |
|---|---|---|---|---|
| `composer app` | `bin/app.php` | `cli-hal-api-app` | Sync (baseline) | `parallel` |
| `composer async` | `bin/async.php` | `cli-hal-api-app` | ext-parallel threads | `parallel` |
| `composer swoole` | `bin/swoole.php` | `prod-swoole-hal-api-app` | ext-swoole coroutines | `swoole` |
| `composer parallel-server` | `bin/parallel-server.php` | `prod-hal-app` | ext-parallel HTTP benchmark harness | `parallel` |

The application's `AppModule` does not know about execution form. The entrypoint
(`bin/*.php`) declares the runtime profile and overrides the appropriate
bootstrap module on top of `AppModule`.

## Database

`docker-compose.yml` presets `DB_DSN=mysql:host=mysql;dbname=demo`
together with `DB_USER=demo` / `DB_PASS=demo` on both app services, and
the bundled MySQL 8.0 service runs on the compose network with a matching
schema. On a host machine, `env.dist.json` and the steady-state benchmark
scripts default to `mysql:host=127.0.0.1;dbname=demo` with the same
`demo` / `demo` credentials, which matches the compose port mapping.
`composer setup` (called automatically by `composer install`)
reads those env vars, drops every table in the `demo` schema, and
re-runs `sql/schema.sql` and `sql/seed.sql` through PDO — so you can
re-seed at any time without recreating the MySQL volume.

To override the defaults (for example to point at a remote MySQL or
fall back to SQLite), drop an `env.json` next to `composer.json`:

```bash
cat > env.json << 'EOF'
{
    "DB_DSN": "sqlite:var/db/blog.sqlite",
    "DB_USER": "",
    "DB_PASS": ""
}
EOF

docker compose exec parallel composer setup    # re-seed against the new DSN
```

`bin/setup.php` knows the SQLite dialect too, so the SQLite path keeps
working when you want a serverless run.

### DI cache

The `parallel` and `swoole` services share `var/tmp` through the bind
mount and the DI cache bakes in both the runtime adapter (so
`ParallelRuntimeModule` from the parallel image is incompatible with
the swoole image's missing `parallel\Runtime`) and the `DB_DSN`
resolved at compile time (so switching between Docker and host
execution can otherwise reuse stale connection info). The steady-state
benchmark script clears the target context cache before starting its
temporary server. `composer setup` clears `var/tmp/*` for you.

## Commands

All demo operations are exposed as composer scripts and run inside the
container that owns the matching extension. Use `composer run --list`
from within the container to discover everything; the common ones are:

```bash
docker compose exec parallel composer setup              # Re-seed MySQL
docker compose exec parallel composer app                # Sync entrypoint (bin/app.php)
docker compose exec parallel composer async              # ext-parallel entrypoint (bin/async.php)
docker compose exec swoole   composer swoole             # ext-swoole HTTP server (bin/swoole.php)
docker compose exec parallel composer parallel-benchmark # ext-parallel benchmark
docker compose exec swoole   composer swoole-benchmark   # ext-swoole benchmark
docker compose exec parallel composer steady-state-parallel # wrk benchmark against ext-parallel server
docker compose exec swoole   composer steady-state-swoole   # wrk benchmark against Swoole server
```

Library quality checks run from the repository root, not from `demo/`:

```bash
cd ..
composer cs
composer test
./vendor/bin/psalm --no-progress
php -d memory_limit=512M ./vendor/bin/phpstan analyse -c phpstan.neon --no-progress
```

If you prefer to stay inside the container, `docker compose exec
parallel bash` (or `swoole`) drops you into a shell where `composer
<script>` works without the prefix.

Composer forwards extra arguments to the underlying script, so you can
pass a method and URI to the entrypoint scripts. Use `--` to keep the
args separate from composer options, and prefix with `APP_CONTEXT=` to
change the runtime context:

```bash
docker compose exec parallel composer async -- get 'app://self/dashboard?user_id=1'
docker compose exec -e APP_CONTEXT=cli-hal-api-app parallel composer async -- get 'app://self/dashboard'
```

The `xprofile` family (`composer xprofile`, `xprofile-parallel`,
`xprofile-swoole`) requires Xdebug, which is not bundled in the demo
images; run those on a host PHP with Xdebug installed if needed.

## Links

- [BEAR.Sunday](http://bearsunday.github.io/)
- [BEAR.Async](https://github.com/bearsunday/BEAR.Async)
