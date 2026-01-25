<?php

declare(strict_types=1);

namespace BEAR\Async;

/**
 * Interface for executing SQL batch queries
 *
 * Implementations may execute queries:
 * - Asynchronously (MysqliBatchExecutor using mysqli_poll)
 * - Synchronously (SyncBatchExecutor for testing/fallback)
 */
interface SqlBatchExecutorInterface
{
    /**
     * Execute all queries
     *
     * @param array<string, array{string, array<string, mixed>}> $queries
     *
     * @return array<string, list<array<string, mixed>>> Results map [key => rows]
     */
    public function execute(array $queries): array;
}
