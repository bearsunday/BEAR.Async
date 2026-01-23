<?php

declare(strict_types=1);

namespace BEAR\Async;

use PHPUnit\Framework\TestCase;

class AdapterTest extends TestCase
{
    public function testAdapterEnumCases(): void
    {
        $cases = Adapter::cases();

        $this->assertCount(3, $cases);
        $this->assertSame('Swoole', Adapter::Swoole->name);
        $this->assertSame('Amp', Adapter::Amp->name);
        $this->assertSame('Sync', Adapter::Sync->name);
    }
}
