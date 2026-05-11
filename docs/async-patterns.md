# Async Code Patterns in BEAR.Async

This document describes the async/coroutine patterns implemented in BEAR.Async and explains why each pattern is important for correct concurrent execution.

## Supported Runtimes

| Runtime | Adapter | Use Case |
|---------|---------|----------|
| Swoole/OpenSwoole | `SwooleAsync` | Coroutine-based HTTP servers |
| ext-parallel | `ParallelAsync` | PHP-FPM/Apache with thread pool |
| None (fallback) | `SyncAsync` | Sequential execution when no async runtime available |

## Pattern Catalog

### 1. WaitGroup for Coroutine Synchronization

**Problem**: When spawning multiple coroutines, the parent may continue execution before all child coroutines complete.

**Solution**: Use WaitGroup to track and wait for all coroutines.

```php
// src/Adapter/SwooleAsync.php
$wg = new WaitGroup();

foreach ($tasks as $task) {
    $wg->add();
    Coroutine::create(function () use ($task, $wg): void {
        try {
            $result = ($task->getRequest())()->body;
            $task->setResult($result);
        } finally {
            $wg->done();  // Always call done(), even on exception
        }
    });
}

$wg->wait();  // Block until all coroutines complete
```

**Key Points**:
- Call `$wg->add()` before creating each coroutine
- Place `$wg->done()` in `finally` block to ensure it's called even on exceptions
- Call `$wg->wait()` after spawning all coroutines

### 2. Double-Checked Locking for Thread-Safe Initialization

**Problem**: In concurrent environments, lazy initialization can cause race conditions where multiple threads/coroutines initialize the same resource simultaneously.

**Solution**: Use double-checked locking with a mutex.

```php
// src/PdoPool.php
public function get(): PDO
{
    if (! $this->initialized) {           // First check (fast path)
        $this->lock->lock();
        try {
            if (! $this->initialized) {   // Second check (after acquiring lock)
                $this->initialize();
                $this->initialized = true;
            }
        } finally {
            $this->lock->unlock();
        }
    }
    // ...
}
```

**Key Points**:
- First check avoids lock overhead when already initialized
- Second check inside lock prevents double initialization
- Always unlock in `finally` block

### 3. Channel-Based Connection Pool

**Problem**: In coroutine environments, sharing a single database connection between coroutines causes protocol violations ("Packets out of order" errors).

**Solution**: Use a Channel-based connection pool where each coroutine borrows and returns connections.

```php
// src/PdoPool.php
private function initialize(): void
{
    $channel = new Channel($this->size);

    for ($i = 0; $i < $this->size; $i++) {
        $pdo = new PDO($this->dsn, $this->user, $this->pass);
        $channel->push($pdo);
    }

    $this->pool = $channel;
}

public function get(): PDO
{
    $pdo = $this->pool->pop($this->timeout);
    if ($pdo === false) {
        throw new PoolTimeoutException();
    }
    return $pdo;
}

public function put(PDO $pdo): void
{
    $this->pool->push($pdo);
}
```

### 4. Timeout with Domain Exceptions

**Problem**: Infinite waits can hang the application. Generic exceptions make error handling difficult.

**Solution**: Always specify timeouts and throw domain-specific exceptions.

```php
// src/PdoPool.php
$pdo = $pool->pop($this->timeout);

if ($pdo === false) {
    throw new PoolTimeoutException();  // Domain-specific, not RuntimeException
}
```

**Domain Exceptions in BEAR.Async**:
- `PoolTimeoutException` - Connection pool timeout
- `NotInCoroutineException` - API called outside coroutine context
- `PoolNotInitializedException` - Pool accessed before initialization
- `BootstrapFileException` - Parallel runtime bootstrap failure

### 5. Coroutine Context Validation

**Problem**: Coroutine-specific APIs fail or behave unexpectedly when called outside a coroutine context.

**Solution**: Check coroutine context before using coroutine APIs.

```php
// src/PooledPdoProvider.php
public function get(): PDO
{
    if (Coroutine::getCid() === -1) {
        throw new NotInCoroutineException();
    }

    $pdo = $this->pool->get();
    Coroutine::defer(fn () => $this->pool->put($pdo));

    return $pdo;
}
```

**Key Points**:
- `Coroutine::getCid() === -1` indicates non-coroutine context
- Throw a clear exception rather than letting the API fail silently

### 6. Automatic Resource Cleanup with defer()

**Problem**: Resources borrowed from a pool must be returned, but exceptions can prevent cleanup code from running.

**Solution**: Use `Coroutine::defer()` to schedule cleanup when the coroutine ends.

```php
// src/PooledPdoProvider.php
$pdo = $this->pool->get();
Coroutine::defer(fn () => $this->pool->put($pdo));

return $pdo;
// PDO is automatically returned to pool when coroutine ends
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

### 8. Adapter Pattern for Graceful Degradation

**Problem**: Applications should work even when async extensions are unavailable.

**Solution**: Define an interface and provide multiple implementations including a sync fallback.

```php
// AsyncInterface defines the contract
interface AsyncInterface
{
    public function __invoke(array $tasks): void;
    public function execute(array $requests): array;
    public function isAvailable(): bool;
}

// Implementations
final class SwooleAsync implements AsyncInterface { /* ... */ }
final class ParallelAsync implements AsyncInterface { /* ... */ }
final class SyncAsync implements AsyncInterface { /* ... */ }  // Fallback
```

**Module Selection**:
- `AsyncSwooleModule` - For Swoole HTTP servers (installed in `AppModule`)
- `AsyncParallelBootstrapModule` - For PHP-FPM/Apache environments (installed via `bin/async.php` → library `bootstrap.php`)
- Auto-fallback to `SyncAsync` when no async runtime detected

## Anti-Patterns to Avoid

### 1. Infinite Wait

```php
// Bad: No timeout
$pdo = $channel->pop();

// Good: With timeout
$pdo = $channel->pop($this->timeout);
if ($pdo === false) {
    throw new PoolTimeoutException();
}
```

### 2. Shared Mutable State Without Synchronization

```php
// Bad: Race condition
if (! $this->initialized) {
    $this->initialize();
    $this->initialized = true;
}

// Good: Double-checked locking
if (! $this->initialized) {
    $this->lock->lock();
    try {
        if (! $this->initialized) {
            $this->initialize();
            $this->initialized = true;
        }
    } finally {
        $this->lock->unlock();
    }
}
```

### 3. Missing WaitGroup done() on Exception

```php
// Bad: done() skipped on exception
Coroutine::create(function () use ($wg) {
    $wg->add();
    doWork();  // If this throws, done() is never called
    $wg->done();
});

// Good: done() in finally
$wg->add();
Coroutine::create(function () use ($wg) {
    try {
        doWork();
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
4. **Mock the pool** to test exception handling paths

Example test structure:
```php
public function testPoolTimeout(): void
{
    Coroutine::run(function (): void {
        $pool = new PdoPool($dsn, $user, $pass, size: 1, timeout: 0.1);

        // Borrow the only connection
        $pdo = $pool->get();

        // Try to borrow another - should timeout
        $this->expectException(PoolTimeoutException::class);
        $pool->get();
    });
}
```
