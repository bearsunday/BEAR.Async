<?php

declare(strict_types=1);

namespace BEAR\Async\Worker;

use PHPUnit\Framework\TestCase;

class WorkerResourceCacheTest extends TestCase
{
    protected function setUp(): void
    {
        WorkerResourceCache::reset();
    }

    protected function tearDown(): void
    {
        WorkerResourceCache::reset();
    }

    public function testIsWorkerStartsFalse(): void
    {
        $this->assertFalse(WorkerResourceCache::isWorker());
    }

    public function testMarkAsWorkerFlipsFlag(): void
    {
        WorkerResourceCache::markAsWorker();

        $this->assertTrue(WorkerResourceCache::isWorker());
    }

    public function testResetClearsState(): void
    {
        WorkerResourceCache::markAsWorker();
        WorkerResourceCache::reset();

        $this->assertFalse(WorkerResourceCache::isWorker());
    }
}
