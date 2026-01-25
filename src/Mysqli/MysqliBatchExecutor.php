<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

use BEAR\Async\SqlBatchExecutorInterface;
use mysqli;
use Override;

use function array_fill_keys;
use function array_keys;
use function mysqli_poll;
use function usleep;

use const MYSQLI_ASSOC;
use const MYSQLI_ASYNC;

/**
 * Executes multiple mysqli queries asynchronously using mysqli_poll
 *
 * This executor uses mysqli's native async support to execute multiple queries
 * in parallel. Each query requires its own connection since mysqli async
 * operations are connection-bound.
 *
 * Usage:
 *   $results = (new SqlBatch($executor, [
 *       'users' => ['SELECT * FROM users WHERE id = :id', ['id' => 1]],
 *       'posts' => ['SELECT * FROM posts WHERE user_id = :user_id', ['user_id' => 1]],
 *   ]))();
 */
final class MysqliBatchExecutor implements SqlBatchExecutorInterface
{
    private const POLL_INTERVAL_USEC = 1000;
    private const MAX_POLL_ITERATIONS = 30000;

    public function __construct(
        private readonly MysqliConnectionFactory $factory,
        private readonly MysqliParamBinder $binder,
    ) {
    }

    /**
     * Execute multiple queries asynchronously
     *
     * @param array<string, array{string, array<string, mixed>}> $queries
     *
     * @return array<string, list<array<string, mixed>>> Results map [key => rows]
     */
    #[Override]
    public function execute(array $queries): array
    {
        if ($queries === []) {
            return [];
        }

        [$asyncConnections, $syncResults] = $this->startQueries($queries);
        $asyncResults = $this->waitForResults($asyncConnections, array_keys($asyncConnections));

        $this->closeConnections($asyncConnections);

        // Merge sync and async results, preserving original order
        $results = [];
        foreach (array_keys($queries) as $key) {
            $results[$key] = $syncResults[$key] ?? $asyncResults[$key] ?? [];
        }

        return $results;
    }

    /**
     * Start all queries (async for simple, sync for parameterized)
     *
     * @param array<string, array{string, array<string, mixed>}> $queries
     *
     * @return array{array<string, mysqli>, array<string, list<array<string, mixed>>>}
     */
    private function startQueries(array $queries): array
    {
        /** @var array<string, mysqli> $asyncConnections */
        $asyncConnections = [];
        /** @var array<string, list<array<string, mixed>>> $syncResults */
        $syncResults = [];

        foreach ($queries as $key => [$sql, $params]) {
            if ($params !== []) {
                // Parameterized queries: execute synchronously (mysqli limitation)
                $syncResults[$key] = $this->executeSyncWithParams($sql, $params);
            } else {
                // Simple queries: execute asynchronously
                $mysqli = $this->factory->create();
                $asyncConnections[$key] = $mysqli;
                $mysqli->query($sql, MYSQLI_ASYNC);
            }
        }

        return [$asyncConnections, $syncResults];
    }

    /**
     * Execute parameterized query synchronously
     *
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function executeSyncWithParams(string $sql, array $params): array
    {
        $mysqli = $this->factory->create();

        try {
            [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);
            $types = $this->binder->buildTypeString($orderedParams);

            $stmt = $mysqli->prepare($convertedSql);
            if ($stmt === false) {
                return [];
            }

            if ($types !== '') {
                $stmt->bind_param($types, ...$orderedParams);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            if ($result === false) {
                $stmt->close();

                return [];
            }

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();
            $stmt->close();

            return $rows;
        } finally {
            $mysqli->close();
        }
    }

    /**
     * Wait for all async queries to complete and collect results
     *
     * @param array<string, mysqli> $connections
     * @param list<string>          $keys
     *
     * @return array<string, list<array<string, mixed>>>
     *
     * @psalm-suppress PossiblyNullArgument mysqli_poll modifies arrays by reference
     * @psalm-suppress ArgumentTypeCoercion mysqli_poll modifies arrays by reference
     */
    private function waitForResults(array $connections, array $keys): array
    {
        if ($connections === []) {
            return [];
        }

        /** @var array<string, list<array<string, mixed>>> $results */
        $results = array_fill_keys($keys, []);
        $pending = $connections;
        $iterations = 0;

        while ($pending !== [] && $iterations < self::MAX_POLL_ITERATIONS) {
            /** @var list<mysqli> $read */
            $read = array_values($pending);
            /** @var list<mysqli> $error */
            $error = array_values($pending);
            /** @var list<mysqli> $reject */
            $reject = array_values($pending);

            $pollResult = mysqli_poll($read, $error, $reject, 0, self::POLL_INTERVAL_USEC);
            if ($pollResult === false || $pollResult === 0) {
                usleep(self::POLL_INTERVAL_USEC);
                $iterations++;
                continue;
            }

            $this->processReadConnections($read, $pending, $results);
            $this->processErrorConnections($error, $pending);
            $this->processErrorConnections($reject, $pending);

            $iterations++;
        }

        return $results;
    }

    /**
     * Process connections that have completed read
     *
     * @param list<mysqli>                                  $read
     * @param array<string, mysqli>                         $pending
     * @param array<string, list<array<string, mixed>>>     $results
     *
     * @psalm-suppress ReferenceConstraintViolation
     */
    private function processReadConnections(array $read, array &$pending, array &$results): void
    {
        foreach ($read as $mysqli) {
            $key = $this->findKeyByConnection($pending, $mysqli);
            if ($key === null) {
                continue;
            }

            $result = $mysqli->reap_async_query();
            if ($result instanceof \mysqli_result) {
                /** @var list<array<string, mixed>> $rows */
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $results[$key] = $rows;
                $result->free();
            }

            unset($pending[$key]);
        }
    }

    /**
     * Process connections that have errors
     *
     * @param list<mysqli>          $connections
     * @param array<string, mysqli> $pending
     *
     * @psalm-suppress ReferenceConstraintViolation
     */
    private function processErrorConnections(array $connections, array &$pending): void
    {
        foreach ($connections as $mysqli) {
            $key = $this->findKeyByConnection($pending, $mysqli);
            if ($key !== null) {
                unset($pending[$key]);
            }
        }
    }

    /**
     * Find the query key by mysqli connection instance
     *
     * @param array<string, mysqli> $connections
     */
    private function findKeyByConnection(array $connections, mysqli $target): string|null
    {
        foreach ($connections as $key => $mysqli) {
            if ($mysqli === $target) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Close all connections
     *
     * @param array<string, mysqli> $connections
     */
    private function closeConnections(array $connections): void
    {
        foreach ($connections as $mysqli) {
            $mysqli->close();
        }
    }
}
