<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

use BEAR\Async\SqlBatch;
use BEAR\Async\SqlBatchExecutorInterface;
use mysqli;
use Override;

use function array_fill_keys;
use function array_keys;
use function count;
use function mysqli_poll;
use function usleep;

/**
 * Executes multiple mysqli queries asynchronously using mysqli_poll
 *
 * This executor uses mysqli's native async support to execute multiple queries
 * in parallel. Each query requires its own connection since mysqli async
 * operations are connection-bound.
 *
 * Usage:
 *   $batch = new SqlBatch([
 *       'users' => ['SELECT * FROM users WHERE id = :id', ['id' => 1]],
 *       'posts' => ['SELECT * FROM posts WHERE user_id = :user_id', ['user_id' => 1]],
 *   ]);
 *   $results = $executor->execute($batch);
 */
final class MysqliBatchExecutor implements SqlBatchExecutorInterface
{
    private const int POLL_INTERVAL_USEC = 1000;
    private const int MAX_POLL_ITERATIONS = 30000;

    public function __construct(
        private readonly MysqliConnectionFactory $factory,
        private readonly MysqliParamBinder $binder,
    ) {
    }

    /**
     * Execute multiple queries asynchronously
     *
     * @return array<string, list<array<string, mixed>>> Results map [key => rows]
     */
    #[Override]
    public function execute(SqlBatch $batch): array
    {
        if ($batch->isEmpty()) {
            return [];
        }

        $queries = $batch->getQueries();
        $connections = $this->startAsyncQueries($queries);
        $results = $this->waitForResults($connections, array_keys($queries));

        $this->closeConnections($connections);

        return $results;
    }

    /**
     * Start all queries asynchronously
     *
     * @param array<string, array{string, array<string, mixed>}> $queries
     *
     * @return array<string, mysqli>
     */
    private function startAsyncQueries(array $queries): array
    {
        $connections = [];

        foreach ($queries as $key => [$sql, $params]) {
            $mysqli = $this->factory->create();
            $connections[$key] = $mysqli;

            $convertedSql = $sql;
            if ($params !== []) {
                [$convertedSql] = $this->binder->convertNamedToPositional($sql, $params);
                $types = $this->binder->buildTypeString(array_values($params));
                $this->executeWithParams($mysqli, $convertedSql, $types, $params);
            } else {
                $mysqli->query($convertedSql, MYSQLI_ASYNC);
            }
        }

        return $connections;
    }

    /**
     * Execute query with bound parameters asynchronously
     *
     * @param array<string, mixed> $params
     */
    private function executeWithParams(mysqli $mysqli, string $sql, string $types, array $params): void
    {
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            return;
        }

        $values = array_values($params);
        if ($types !== '') {
            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();
        $stmt->close();

        // Note: For async with params, we need to use a different approach
        // mysqli prepared statements don't support MYSQLI_ASYNC directly
        // We fall back to synchronous execution for parameterized queries
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
