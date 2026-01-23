<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

class CrawlTaskTest extends TestCase
{
    public function testGetHash(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $task = new CrawlTask('test-hash', $request);

        $this->assertSame('test-hash', $task->getHash());
    }

    public function testGetRequest(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $task = new CrawlTask('test-hash', $request);

        $this->assertSame($request, $task->getRequest());
    }

    public function testGetResultIsNullInitially(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $task = new CrawlTask('test-hash', $request);

        $this->assertNull($task->getResult());
    }

    public function testSetResult(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $task = new CrawlTask('test-hash', $request);
        $result = ['key' => 'value'];

        $task->setResult($result);

        $this->assertSame($result, $task->getResult());
    }

    public function testAddTargetAndSetResultPropagates(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $task = new CrawlTask('test-hash', $request);
        $body = ['existing' => 'data'];

        $task->addTarget($body, 'posts');

        $result = ['id' => 1, 'title' => 'Post'];
        $task->setResult($result);

        $this->assertSame($result, $body['posts']);
    }

    public function testGetTargets(): void
    {
        $invoker = $this->createMock(InvokerInterface::class);
        $ro = $this->createMock(ResourceObject::class);
        $request = new Request($invoker, $ro, 'get', []);

        $task = new CrawlTask('test-hash', $request);

        $this->assertSame([], $task->getTargets());
    }
}
