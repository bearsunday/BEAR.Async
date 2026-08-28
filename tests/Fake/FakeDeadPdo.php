<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO whose connection has been killed by the server
 *
 * Backed by a real in-memory SQLite handle, but every query() fails the
 * way a MySQL connection does after a restart or wait_timeout.
 */
final class FakeDeadPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    public function query(string $query, int|null $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        unset($query, $fetchMode, $fetchModeArgs);

        throw new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
    }
}
