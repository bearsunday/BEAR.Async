<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

use function count;
use function iterator_to_array;

class FutureResourceTest extends TestCase
{
    public function testGetId(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $this->assertSame('test-id', $future->getId());
    }

    public function testGetRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $this->assertSame($request, $future->getRequest());
    }

    public function testIsResolvedReturnsFalseInitially(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $this->assertFalse($future->isResolved());
    }

    public function testResolveAndIsResolved(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['data' => 'value'];

        $future->resolve($result);

        $this->assertTrue($future->isResolved());
    }

    public function testAwaitReturnsResolvedResult(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['data' => 'value'];

        $future->resolve($result);

        $this->assertSame($result, $future->await());
    }

    public function testAwaitFallbackToSyncExecution(): void
    {
        $resultRo = new FakeResourceObject();
        $resultRo->body = ['sync' => 'result'];

        $invoker = $this->createMock(InvokerInterface::class);
        $invoker->method('invoke')
            ->willReturn($resultRo);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        // Not resolved, so await() should execute synchronously
        $result = $future->await();

        $this->assertSame(['sync' => 'result'], $result->body);
    }

    public function testOffsetGet(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['name' => 'John', 'age' => 30];

        $future->resolve($result);

        $this->assertSame('John', $future['name']);
        $this->assertSame(30, $future['age']);
    }

    public function testOffsetGetNonExistentKey(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['name' => 'John'];

        $future->resolve($result);

        $this->assertNull($future['nonexistent']);
    }

    public function testOffsetExists(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['name' => 'John'];

        $future->resolve($result);

        $this->assertTrue(isset($future['name']));
        $this->assertFalse(isset($future['nonexistent']));
    }

    public function testOffsetSetIsNoOp(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['name' => 'John'];

        $future->resolve($result);

        // offsetSet should be no-op (read-only)
        $future['name'] = 'Jane';

        // Value should be unchanged
        $this->assertSame('John', $future['name']);
    }

    public function testOffsetUnsetIsNoOp(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['name' => 'John'];

        $future->resolve($result);

        // offsetUnset should be no-op (read-only)
        unset($future['name']);

        // Value should still exist
        $this->assertTrue(isset($future['name']));
    }

    public function testCount(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['a' => 1, 'b' => 2, 'c' => 3];

        $future->resolve($result);

        $this->assertCount(3, $future);
    }

    public function testCountWithNonCountableBody(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = 'not an array'; // @phpstan-ignore-line

        $future->resolve($result);

        $this->assertSame(0, count($future));
    }

    public function testGetIterator(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = ['a' => 1, 'b' => 2];

        $future->resolve($result);

        $iterated = iterator_to_array($future);

        $this->assertSame(['a' => 1, 'b' => 2], $iterated);
    }

    public function testGetIteratorWithNonArrayBody(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $future = new FutureResource('test-id', $request);

        $result = new FakeResourceObject();
        $result->body = 'not an array'; // @phpstan-ignore-line

        $future->resolve($result);

        $iterated = iterator_to_array($future);

        $this->assertSame([], $iterated);
    }
}
