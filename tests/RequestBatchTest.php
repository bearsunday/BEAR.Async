<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Fake\FakeResourceObject;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use PHPUnit\Framework\TestCase;

class RequestBatchTest extends TestCase
{
    private RequestBatch $batch;

    protected function setUp(): void
    {
        $this->batch = new RequestBatch();
    }

    public function testIsEmptyInitially(): void
    {
        $this->assertTrue($this->batch->isEmpty());
    }

    public function testGetTasksReturnsEmptyArrayInitially(): void
    {
        $this->assertSame([], $this->batch->getTasks());
    }

    public function testAddCreatesTask(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $body = ['id' => 1];
        $this->batch->add($request, 'posts', $body);

        $this->assertFalse($this->batch->isEmpty());
        $tasks = $this->batch->getTasks();
        $this->assertCount(1, $tasks);
    }

    public function testAddDeduplicatesSameRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = new FakeResourceObject();
        $request = new Request($invoker, $ro, 'get', []);

        $body1 = ['id' => 1];
        $body2 = ['id' => 2];

        $this->batch->add($request, 'posts', $body1);
        $this->batch->add($request, 'posts', $body2);

        $tasks = $this->batch->getTasks();
        $this->assertCount(1, $tasks);

        $task = array_values($tasks)[0];
        $this->assertCount(2, $task->getTargets());
    }

    public function testAddDifferentRequestsCreatesSeparateTasks(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro1 = new FakeResourceObject();
        $ro2 = new FakeResourceObject();

        $request1 = new Request($invoker, $ro1, 'get', ['id' => 1]);
        $request2 = new Request($invoker, $ro2, 'get', ['id' => 2]);

        $body1 = ['user' => 'a'];
        $body2 = ['user' => 'b'];

        $this->batch->add($request1, 'posts', $body1);
        $this->batch->add($request2, 'posts', $body2);

        $tasks = $this->batch->getTasks();
        $this->assertCount(2, $tasks);
    }
}
