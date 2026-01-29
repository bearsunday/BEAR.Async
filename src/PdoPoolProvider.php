<?php

declare(strict_types=1);

namespace BEAR\Async;

use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;
use Swoole\Database\PDOConfig;
use Swoole\Database\PDOPool;

/**
 * Provider for Swoole\Database\PDOPool
 *
 * Creates a PDOPool using Swoole's built-in connection pool implementation.
 *
 * @implements ProviderInterface<PDOPool>
 */
final class PdoPoolProvider implements ProviderInterface
{
    /**
     * @param non-empty-string $dsn      PDO DSN string (mysql:host=localhost;dbname=test)
     * @param string           $user     Database username
     * @param string           $pass     Database password
     * @param positive-int     $poolSize Pool size (number of connections)
     */
    public function __construct(
        #[Named('pdo_pool_dsn')] private readonly string $dsn,
        #[Named('pdo_pool_user')] private readonly string $user,
        #[Named('pdo_pool_pass')] private readonly string $pass,
        #[Named('pdo_pool_size')] private readonly int $poolSize,
    ) {
    }

    public function get(): PDOPool
    {
        $config = $this->createConfig();

        return new PDOPool($config, $this->poolSize);
    }

    private function createConfig(): PDOConfig
    {
        $parsed = $this->parseDsn($this->dsn);
        $config = new PDOConfig();

        if (isset($parsed['driver'])) {
            $config = $config->withDriver($parsed['driver']);
        }

        if (isset($parsed['host'])) {
            $config = $config->withHost($parsed['host']);
        }

        if (isset($parsed['port'])) {
            $config = $config->withPort((int) $parsed['port']);
        }

        if (isset($parsed['dbname'])) {
            $config = $config->withDbname($parsed['dbname']);
        }

        if (isset($parsed['charset'])) {
            $config = $config->withCharset($parsed['charset']);
        }

        if (isset($parsed['unix_socket'])) {
            $config = $config->withUnixSocket($parsed['unix_socket']);
        }

        return $config
            ->withUsername($this->user)
            ->withPassword($this->pass);
    }

    /**
     * Parse PDO DSN string into components
     *
     * @return array<string, string>
     */
    private function parseDsn(string $dsn): array
    {
        $result = [];

        // Extract driver (e.g., "mysql:" from "mysql:host=localhost;dbname=test")
        $colonPos = strpos($dsn, ':');
        if ($colonPos !== false) {
            $result['driver'] = substr($dsn, 0, $colonPos);
            $dsn = substr($dsn, $colonPos + 1);
        }

        // Parse key=value pairs
        $pairs = explode(';', $dsn);
        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            $eqPos = strpos($pair, '=');
            if ($eqPos !== false) {
                $key = trim(substr($pair, 0, $eqPos));
                $value = trim(substr($pair, $eqPos + 1));
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
