# Async Code Patterns in BEAR.Async

This document describes the async/coroutine patterns implemented in BEAR.Async and explains why each pattern is important for correct concurrent execution.

## Supported Runtimes

| Runtime | Adapter | Use Case |
|---------|---------|----------|
| Swoole/OpenSwoole | `SwooleAsync` | Coroutine-based HTTP servers |
| ext-parallel | `ParallelAsync` | PHP-FPM/Apache with thread pool |
| None | `SyncAsync` | Explicit sequential choice / standard bear/resource behavior |

There is no runtime auto-fallback: `AsyncInterface` has no `isAvailable()` check. If a Swoole or parallel module is installed but its extension is missing, module `configure()` fails fast with `ExtensionNotLoadedException` rather than silently degrading to `SyncAsync`.

## Pattern Catalog

### 1. WaitGroup for Coroutine Synchronization

**Problem**: When spawning multiple coroutines, the parent may continue execution before all child coroutines complete. Worse, in Swoole, an uncaught `Throwable` inside a coroutine is a process-level fatal error — it kills the worker process and every other concurrent request being served by it, not just the failing task.

**Solution**: Use WaitGroup to track and wait for all coroutines, and catch every `Throwable` inside each coroutine so one failing task can never crash the worker. Errors are collected and the first one is rethrown to the caller only after every coroutine has finished.

```php
// src/Adapter/SwooleAsync.php
$wg = new WaitGroup();
/** @var array<string, Throwable> $errors */
$errors = [];

foreach ($tasks as $key => $task) {
    $wg->add();
    Coroutine::create(function () use ($key, $task, $wg, &$errors): void {
        try {
            if (! ($task instanceof RequestTask)) {
                return;
            }

            // For crawl: get body and set result
            $result = ($task->getRequest())()->body;
            $task->setResult($result);
        } catch (Throwable $e) {
            $errors[$key] = $e;
        } finally {
            $wg->done();
        }
    });
}

$wg->wait();

if ($errors !== []) {
    throw $errors[array_key_first($errors)];
}
```

**Key Points**:
- Call `$wg->add()` before creating each coroutine
- Catch `Throwable` inside the coroutine body — an uncaught exception in a Swoole coroutine is a **process-level fatal**, not a normal PHP exception unwind. It kills the entire worker, taking down every other request currently being served concurrently, not just the failing task
- Place `$wg->done()` in a `finally` block so it always runs, on success or failure, keeping the WaitGroup count accurate
- Call `$wg->wait()` after spawning all coroutines, so every task — successful or not — is allowed to run to completion
- Only after all coroutines finish does the adapter rethrow the first collected error, preserving its original exception type

### 2. Delegate Pooling to Swoole's PDOPool

**Problem**: Hand-rolling a connection pool (channel management, sizing, connection construction) duplicates logic that the runtime already provides correctly and is easy to get subtly wrong (initialization races, leaked connections, resize bugs).

**Solution**: Build the pool with Swoole's own `Swoole\Database\PDOPool`, configured from DI-bound parameters, and keep BEAR.Async's own code focused on checkout semantics on top of it.

```php
// src/PdoPoolProvider.php
final class PdoPoolProvider implements ProviderInterface
{
    public function __construct(
        #[Named('pdo_pool_dsn')] private readonly string $dsn,
        #[Named('pdo_pool_user')] private readonly string $user,
        #[Named('pdo_pool_pass')] private readonly string $pass,
        #[Named('pdo_pool_size')] private readonly int $poolSize,
    ) {
    }

    public function get(): PDOPool
    {
        $config = $this->createConfig();

        return new PDOPool($config, $this->poolSize);
    }
    // parses the DSN into a PDOConfig (driver/host/port/dbname/charset/unix_socket)
    // and applies username/password ...
}
```

**Key Points**:
- `PdoPoolProvider` builds a `PDOConfig` from the DSN and hands the pool construction entirely to Swoole — no custom `Channel` bookkeeping in userland
- Pool size, DSN, user, and password are DI-bound (`#[Named(...)]`) rather than hardcoded, so `PdoPoolModule` can wire them per application
- Checkout/return semantics (borrowing, liveness checks, coroutine-scoped caching) live in `PooledPdoBorrower`, described next — the pool itself only owns the connections

### 3. Ping-on-Checkout Self-Healing

**Problem**: `Swoole\Database\PDOPool` does not validate a connection when it hands it out. If the database restarts, fails over, or closes an idle connection (MySQL `wait_timeout`), the pool can keep returning a dead `PDOProxy` indefinitely, and every borrower gets a `PDOException` on first use.

**Solution**: `PooledPdoBorrower` pings every checked-out connection with `SELECT 1` before handing it to the caller. If the connection is dead, it is discarded (`$pool->put(null)` frees the slot without returning a broken connection) and checkout is retried once. If the retry is also dead, a `StalePooledConnectionException` surfaces the problem instead of looping forever.

```php
// src/PooledPdoBorrower.php
private function checkoutLive(): array
{
    [$proxy, $pdo] = $this->checkoutOnce();
    if ($this->isAlive($pdo)) {
        return [$proxy, $pdo];
    }

    // Discard the dead proxy: free the slot instead of returning a dead connection.
    $this->pool->put(null);

    [$proxy, $pdo] = $this->checkoutOnce();
    if ($this->isAlive($pdo)) {
        return [$proxy, $pdo];
    }

    $this->pool->put(null);

    throw new StalePooledConnectionException(
        'PDO pool exhausted: pooled connections are stale (e.g. the database was restarted)',
    );
}

private function isAlive(PDO $pdo): bool
{
    try {
        return $pdo->query('SELECT 1') !== false;
    } catch (PDOException) {
        return false;
    }
}
```

`PooledPdoBorrower::borrow()` also caches the checked-out `PDO` in the current coroutine's context (keyed by `CONTEXT_PDO`), so repeated calls within the same coroutine reuse the same connection instead of exhausting the pool, and registers exactly one `Coroutine::defer()` to return the underlying `PDOProxy` when the coroutine ends.

**Key Points**:
- Never trust a pooled connection is alive just because the pool handed it out — ping it first
- Discard-and-retry once, then fail loudly with a domain exception (`StalePooledConnectionException`) rather than retrying forever or returning a connection that will immediately error
- Coroutine-context caching avoids checking out more than one connection per coroutine, even if multiple providers (`PooledPdoProvider`, `PooledExtendedPdoProvider`) ask for a PDO in the same coroutine

### 4. Timeout with Domain Exceptions

**Problem**: Infinite waits can hang the application. Generic exceptions make error handling difficult.

**Solution**: Always specify timeouts and throw domain-specific exceptions.

```php
// src/PooledPdoBorrower.php
private function checkoutOnce(): array
{
    $proxy = $this->pool->get($this->borrowTimeout);
    if ($proxy === false) {
        throw new PoolTimeoutException(sprintf(
            'PDO pool exhausted: no connection within %.1fs',
            $this->borrowTimeout,
        ));
    }

    assert($proxy instanceof PDOProxy);

    return [$proxy, PdoProxyExtractor::extract($proxy)];
}
```

**Domain Exceptions in BEAR.Async**:
- `PoolTimeoutException` - Connection pool borrow timeout
- `NotInCoroutineException` - API called outside coroutine context
- `StalePooledConnectionException` - Pool kept handing out dead connections
- `TaskNotDispatchedException` - Worker Runtime did not accept a task
- `RecursiveWorkerSpawnException` - Parallel module installed inside a worker

### 5. Coroutine Context Validation

**Problem**: Coroutine-specific APIs fail or behave unexpectedly when called outside a coroutine context.

**Solution**: Check coroutine context before using coroutine APIs. `PooledPdoProvider` itself is now a thin delegate — the check lives in the shared `PooledPdoBorrower`.

```php
// src/PooledPdoProvider.php
final class PooledPdoProvider implements ProviderInterface
{
    private readonly PooledPdoBorrower $borrower;

    public function __construct(
        PDOPool $pool,
        #[Named('pdo_pool_borrow_timeout')] float $borrowTimeout,
    ) {
        $this->borrower = new PooledPdoBorrower($pool, $borrowTimeout);
    }

    public function get(): PDO
    {
        return $this->borrower->borrow();
    }
}
```

```php
// src/PooledPdoBorrower.php
public function borrow(): PDO
{
    if (Coroutine::getCid() === -1) {
        throw new NotInCoroutineException();
    }
    // ... checkout from context cache or pool ...
}
```

**Key Points**:
- `Coroutine::getCid() === -1` indicates non-coroutine context
- Throw a clear exception rather than letting the API fail silently
- Both `PooledPdoProvider` and `PooledExtendedPdoProvider` (`src/Module/PooledExtendedPdoProvider.php`) construct their own `PooledPdoBorrower` and delegate to it, so the context check and checkout logic exist in exactly one place

### 6. Automatic Resource Cleanup with defer()

**Problem**: Resources borrowed from a pool must be returned, but exceptions can prevent cleanup code from running.

**Solution**: Use `Coroutine::defer()` to schedule cleanup when the coroutine ends. `PooledPdoBorrower` registers exactly one `defer()` per coroutine, even though multiple providers may call `borrow()` within it.

```php
// src/PooledPdoBorrower.php
[$proxy, $pdo] = $this->checkoutLive();

$context[self::CONTEXT_PROXY] = $proxy;
$context[self::CONTEXT_PDO] = $pdo;

$pool = $this->pool;
Coroutine::defer(static function () use ($context, $proxy, $pool): void {
    unset(
        $context[PooledPdoBorrower::CONTEXT_PROXY],
        $context[PooledPdoBorrower::CONTEXT_PDO],
        $context[PooledPdoBorrower::CONTEXT_EXTENDED_PDO],
    );
    $pool->put($proxy);
});

return $pdo;
// The underlying PDOProxy is automatically returned to the pool when the coroutine ends
```

### 7. Coroutine-Local Storage

**Problem**: Request-scoped state (like pending async requests) must not leak between concurrent HTTP requests in a coroutine server.

**Solution**: Use `Coroutine::getContext()` for coroutine-local storage.

```php
// src/SwoolePendingRequestsProvider.php
public function get(): PendingRequests
{
    $context = Coroutine::getContext();

    if (! isset($context[self::CONTEXT_KEY])) {
        $context[self::CONTEXT_KEY] = new PendingRequests($this->async);
    }

    return $context[self::CONTEXT_KEY];
}
```

**Key Points**:
- Each coroutine has its own context ArrayObject
- Context is automatically cleaned up when coroutine ends
- Use unique keys to avoid collisions

### 8. Adapter Pattern with Fail-Fast Selection

**Problem**: Applications should behave predictably when async extensions are unavailable — a silent fallback to sequential execution hides a real performance regression.

**Solution**: Define an interface with multiple implementations, and select one explicitly at module-install time. Missing extensions fail fast at `configure()` with `ExtensionNotLoadedException` instead of degrading silently.

```php
// AsyncInterface defines the contract
interface AsyncInterface
{
    public function __invoke(array $tasks): void;
    public function execute(array $requests): array;
}

// Implementations
final class SwooleAsync implements AsyncInterface { /* ... */ }
final class ParallelAsync implements AsyncInterface { /* ... */ }
final class SyncAsync implements AsyncInterface { /* ... */ }  // Explicit sequential choice
```

**Module Selection**:
- `AsyncSwooleModule` - For Swoole HTTP servers (installed in `AppModule`); throws `ExtensionNotLoadedException` without ext-swoole
- `bin/async.php` + library `bootstrap.php` - For resident worker processes using ext-parallel; throws `ExtensionNotLoadedException` without ext-parallel
- No module installed - Standard sequential bear/resource behavior (there is deliberately no runtime auto-fallback)

## Anti-Patterns to Avoid

### 1. Infinite Wait

```php
// Bad: No timeout
$proxy = $pool->get();

// Good: With timeout, via PooledPdoBorrower::checkoutOnce()
$proxy = $this->pool->get($this->borrowTimeout);
if ($proxy === false) {
    throw new PoolTimeoutException(sprintf(
        'PDO pool exhausted: no connection within %.1fs',
        $this->borrowTimeout,
    ));
}
```

### 2. Trusting a Pooled Connection Without Checking It's Alive

```php
// Bad: hand out whatever the pool returns, even if the database restarted
$proxy = $this->pool->get($this->borrowTimeout);
return PdoProxyExtractor::extract($proxy);

// Good: ping-on-checkout, discard-and-retry once (PooledPdoBorrower::checkoutLive())
[$proxy, $pdo] = $this->checkoutOnce();
if ($this->isAlive($pdo)) {
    return [$proxy, $pdo];
}

$this->pool->put(null); // discard the dead connection, free the slot
[$proxy, $pdo] = $this->checkoutOnce();
if ($this->isAlive($pdo)) {
    return [$proxy, $pdo];
}

throw new StalePooledConnectionException(/* ... */);
```

### 3. Missing WaitGroup done() or catch() on Exception

```php
// Bad: done() skipped on exception
Coroutine::create(function () use ($wg) {
    $wg->add();
    doWork();  // If this throws, done() is never called and the exception is uncaught
    $wg->done();
});

// Still bad: done() runs, but the exception is still uncaught — it crashes the
// worker process (and every other request it's serving) once it propagates
// out of the coroutine
Coroutine::create(function () use ($wg) {
    try {
        doWork();
    } finally {
        $wg->done();
    }
});

// Good: catch every Throwable, record it, and rethrow only after wg->wait()
// (see SwooleAsync::__invoke())
Coroutine::create(function () use ($wg, &$errors, $key) {
    try {
        doWork();
    } catch (Throwable $e) {
        $errors[$key] = $e;
    } finally {
        $wg->done();
    }
});
```

### 4. Generic Exceptions

```php
// Bad: Message-based distinction
throw new RuntimeException('Pool timeout');
throw new RuntimeException('Not in coroutine');

// Good: Type-based distinction
throw new PoolTimeoutException();
throw new NotInCoroutineException();
```

## Testing Considerations

When testing async code:

1. **Use `Coroutine::run()`** to create a coroutine context for tests
2. **Test timeout scenarios** by creating pool contention
3. **Test context checks** by calling APIs outside coroutine context
4. **Use a real pool with extreme settings** (size 1, tiny `borrowTimeout`) to exercise exception paths — this project bans mocks; external services run in Docker, internal seams use Fake classes

Example test structure:
```php
public function testPoolTimeout(): void
{
    Coroutine::run(function (): void {
        $config = (new PDOConfig())
            ->withDriver('mysql')
            ->withHost('127.0.0.1')
            ->withDbname('test')
            ->withUsername($user)
            ->withPassword($pass);
        $pool = new PDOPool($config, 1); // pool size 1
        $borrower = new PooledPdoBorrower($pool, borrowTimeout: 0.1);

        // Borrow the only connection
        $pdo = $borrower->borrow();

        // Try to borrow another in a different coroutine - should timeout
        $this->expectException(PoolTimeoutException::class);
        Coroutine::create(fn () => $borrower->borrow());
    });
}
```
