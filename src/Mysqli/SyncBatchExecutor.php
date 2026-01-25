<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

use BEAR\Async\SqlBatch;
use BEAR\Async\SqlBatchExecutorInterface;
use mysqli;
use Override;

use const MYSQLI_ASSOC;

/**
 * Executes SQL batch queries synchronously (sequential execution)
 *
 * This executor is useful for:
 * - Testing without async complexity
 * - Fallback when async is not available
 * - Environments where mysqli async is not supported
 */
final class SyncBatchExecutor implements SqlBatchExecutorInterface
{
    public function __construct(
        private readonly MysqliConnectionFactory $factory,
        private readonly MysqliParamBinder $binder,
    ) {
    }

    /** @return array<string, list<array<string, mixed>>> */
    #[Override]
    public function execute(SqlBatch $batch): array
    {
        if ($batch->isEmpty()) {
            return [];
        }

        $results = [];
        $mysqli = $this->factory->create();

        try {
            foreach ($batch->getQueries() as $key => [$sql, $params]) {
                $results[$key] = $this->executeQuery($mysqli, $sql, $params);
            }
        } finally {
            $mysqli->close();
        }

        return $results;
    }

    /**
     * Execute a single query synchronously
     *
     * @param array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function executeQuery(mysqli $mysqli, string $sql, array $params): array
    {
        if ($params === []) {
            $result = $mysqli->query($sql);
            if (! $result instanceof \mysqli_result) {
                return [];
            }

            /** @var list<array<string, mixed>> $rows */
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();

            return $rows;
        }

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
    }
}
