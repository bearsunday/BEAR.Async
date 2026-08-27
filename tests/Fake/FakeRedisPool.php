<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use Redis;
use RedisException;
use Swoole\Database\RedisPool;

use function array_shift;

/**
 * RedisPool whose checkouts are scripted
 *
 * get() hands out the queued connections in order and returns false (borrow
 * timeout) once the queue is empty; put() only records what came back.
 * The parent constructor is skipped so no real server is dialed.
 */
final class FakeRedisPool extends RedisPool
{
    /** @var list<Redis|null> put() arguments in call order */
    public array $putLog = [];

    public int $getCount = 0;

    /** Simulates put(null)'s synchronous replacement dial failing */
    public bool $throwOnDiscard = false;

    /** @param list<Redis> $checkouts */
    public function __construct(private array $checkouts)
    {
    }

    public function get(float $timeout = -1)
    {
        unset($timeout);
        $this->getCount++;

        return $this->checkouts === [] ? false : array_shift($this->checkouts);
    }

    public function put($connection): void
    {
        $this->putLog[] = $connection;
        if ($connection === null && $this->throwOnDiscard) {
            throw new RedisException('Connection refused');
        }
    }
}
