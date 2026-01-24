<?php

declare(strict_types=1);

namespace BEAR\Async;

use ArrayAccess;
use ArrayIterator;
use BEAR\Resource\AbstractRequest;
use BEAR\Resource\ResourceObject;
use Countable;
use IteratorAggregate;
use Override;
use ReturnTypeWillChange;

use function is_array;
use function is_countable;

/**
 * FutureResource is a lazy-resolution wrapper for async resource requests
 *
 * This class wraps an AbstractRequest and defers execution until the result is needed.
 * When accessed before resolution, it falls back to synchronous execution.
 *
 * @phpstan-implements ArrayAccess<string, mixed>
 * @phpstan-implements IteratorAggregate<int|string, mixed>
 */
final class FutureResource implements ArrayAccess, Countable, IteratorAggregate
{
    private ResourceObject|null $result = null;

    public function __construct(
        private readonly string $id,
        private readonly AbstractRequest $request,
    ) {
    }

    /** Get the unique identifier for this future */
    public function getId(): string
    {
        return $this->id;
    }

    /** Get the underlying request */
    public function getRequest(): AbstractRequest
    {
        return $this->request;
    }

    /** Check if this future has been resolved */
    public function isResolved(): bool
    {
        return $this->result !== null;
    }

    /** Resolve this future with a result */
    public function resolve(ResourceObject $result): void
    {
        $this->result = $result;
    }

    /** Await the result (returns immediately if resolved, falls back to sync execution if not) */
    public function await(): ResourceObject
    {
        if ($this->result !== null) {
            return $this->result;
        }

        // Fallback to synchronous execution
        return ($this->request)();
    }

    /** @param string $offset */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        $result = $this->await();

        return is_array($result->body) ? $result->body[$offset] ?? null : null;
    }

    /** @param string $offset */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        $result = $this->await();

        return is_array($result->body) && isset($result->body[$offset]);
    }

    /**
     * @param string $offset
     * @param mixed  $value
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        // FutureResource is read-only
    }

    /** @param string $offset */
    #[Override]
    #[ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        // FutureResource is read-only
    }

    #[Override]
    public function count(): int
    {
        $result = $this->await();

        return is_countable($result->body) ? count($result->body) : 0;
    }

    /** @return ArrayIterator<int|string, mixed> */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        $result = $this->await();

        return is_array($result->body) ? new ArrayIterator($result->body) : new ArrayIterator([]);
    }
}
