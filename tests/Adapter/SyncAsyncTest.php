<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use PHPUnit\Framework\TestCase;

class SyncAsyncTest extends TestCase
{
    private SyncAsync $syncAsync;

    protected function setUp(): void
    {
        $this->syncAsync = new SyncAsync();
    }

    public function testIsAvailable(): void
    {
        $this->assertTrue($this->syncAsync->isAvailable());
    }

    public function testInvokeWithEmptyTasks(): void
    {
        ($this->syncAsync)([]);
        $this->assertTrue(true); // No exception thrown
    }
}
