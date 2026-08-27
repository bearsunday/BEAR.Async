<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use Redis;

/**
 * Unconnected Redis whose ping() always succeeds
 */
final class FakeLiveRedis extends Redis
{
    public int $pingCount = 0;

    public function ping(string|null $message = null): Redis|string|bool
    {
        unset($message);
        $this->pingCount++;

        return true;
    }
}
