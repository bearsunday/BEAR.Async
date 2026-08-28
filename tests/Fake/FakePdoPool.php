<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use PDOException;
use Swoole\Database\PDOPool;
use Swoole\Database\PDOProxy;

use function array_shift;

/**
 * PDOPool whose checkouts are scripted
 *
 * get() hands out the queued proxies in order and returns false (borrow
 * timeout) once the queue is empty; put() only records what came back.
 * The parent constructor is skipped so no real database is dialed.
 */
final class FakePdoPool extends PDOPool
{
    /** @var list<PDOProxy|null> put() arguments in call order */
    public array $putLog = [];

    public int $getCount = 0;

    /** Simulates put(null)'s synchronous replacement dial failing */
    public bool $throwOnDiscard = false;

    /** @param list<PDOProxy> $checkouts */
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
            throw new PDOException('SQLSTATE[HY000] [2002] Connection refused');
        }
    }
}
