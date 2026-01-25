<?php

declare(strict_types=1);

namespace BEAR\Projection;

use BEAR\Async\SqlBatchExecutorInterface;

final class FakeSqlBatchExecutor implements SqlBatchExecutorInterface
{
    /** @var array<int|string, list<array<string, mixed>>> */
    public array $results = [];

    /** @var array<int|string, array{string, array<string, mixed>}> */
    public array $executedQueries = [];

    public function execute(array $queries): array
    {
        $this->executedQueries = $queries;

        /** @var array<string, list<array<string, mixed>>> */
        $result = $this->results;

        return $result;
    }
}
