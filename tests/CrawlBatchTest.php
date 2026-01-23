<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use PHPUnit\Framework\TestCase;

class CrawlBatchTest extends TestCase
{
    private CrawlBatch $batch;

    protected function setUp(): void
    {
        $this->batch = new CrawlBatch();
    }

    public function testIsEmptyInitially(): void
    {
        $this->assertTrue($this->batch->isEmpty());
    }

    public function testGetTasksReturnsEmptyArrayInitially(): void
    {
        $this->assertSame([], $this->batch->getTasks());
    }
}
