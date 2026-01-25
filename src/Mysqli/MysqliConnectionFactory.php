<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

use BEAR\Async\Exception\MysqliConnectionException;
use mysqli;

/**
 * Factory for creating mysqli connections for async query execution
 */
final class MysqliConnectionFactory
{
    /**
     * @param string   $host     MySQL host
     * @param string   $user     Database username
     * @param string   $pass     Database password
     * @param string   $database Database name
     * @param int|null $port     MySQL port (null for default)
     * @param string   $socket   MySQL socket path
     * @param string   $charset  Character set (default: utf8mb4)
     */
    public function __construct(
        private readonly string $host,
        private readonly string $user,
        private readonly string $pass,
        private readonly string $database,
        private readonly int|null $port = null,
        private readonly string $socket = '',
        private readonly string $charset = 'utf8mb4',
    ) {
    }

    /**
     * Create a new mysqli connection for async query execution
     *
     * @throws MysqliConnectionException
     */
    public function create(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        $mysqli = new mysqli();
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

        try {
            $connected = $mysqli->real_connect(
                $this->host,
                $this->user,
                $this->pass,
                $this->database,
                $this->port ?? 3306,
                $this->socket,
                MYSQLI_CLIENT_FOUND_ROWS,
            );

            if (! $connected) {
                throw new MysqliConnectionException($mysqli->connect_error ?? 'Connection failed');
            }

            $mysqli->set_charset($this->charset);
        } catch (\mysqli_sql_exception $e) {
            throw new MysqliConnectionException($e->getMessage(), 0, $e);
        }

        return $mysqli;
    }
}
