<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class EmbedTaskTest extends TestCase
{
    public function testGetRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $task = new EmbedTask($request);

        $this->assertSame($request, $task->getRequest());
    }

    public function testGetResultReturnsNullInitially(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $task = new EmbedTask($request);

        $this->assertNull($task->getResult());
    }

    public function testSetAndGetResult(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $task = new EmbedTask($request);
        $task->setResult(['key' => 'value']);

        $this->assertSame(['key' => 'value'], $task->getResult());
    }

    public function testSetResultWithNull(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $task = new EmbedTask($request);
        $task->setResult(['key' => 'value']);
        $task->setResult(null);

        $this->assertNull($task->getResult());
    }
}
