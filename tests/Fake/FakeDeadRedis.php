<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use Redis;
use RedisException;

/**
 * Unconnected Redis whose ping() fails the way a dead pooled connection does
 */
final class FakeDeadRedis extends Redis
{
    public function ping(string|null $message = null): Redis|string|bool
    {
        unset($message);

        throw new RedisException('Redis server went away');
    }
}
