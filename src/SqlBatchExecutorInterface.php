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
     * Execute all queries in the batch
     *
     * @return array<string, list<array<string, mixed>>> Results map [key => rows]
     */
    public function execute(SqlBatch $batch): array;
}
