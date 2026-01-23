<?php

declare(strict_types=1);

namespace BEAR\Async;

use PHPUnit\Framework\TestCase;

final class AsyncTest extends TestCase
{
    protected Async $async;

    protected function setUp(): void
    {
        $this->async = new Async();
    }

    public function testIsInstanceOfAsync(): void
    {
        $actual = $this->async;
        $this->assertInstanceOf(Async::class, $actual);
    }
}
