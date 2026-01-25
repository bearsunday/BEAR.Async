<?php

declare(strict_types=1);

namespace BEAR\Async;

use function count;

/**
 * Invocable SQL batch executor
 *
 * Usage:
 *   $results = (new SqlBatch($executor, [
 *       'users' => ['SELECT * FROM users WHERE id = :id', ['id' => 1]],
 *       'posts' => ['SELECT * FROM posts WHERE user_id = :user_id', ['user_id' => 1]],
 *   ]))();
 */
final class SqlBatch
{
    /**
     * @param SqlBatchExecutorInterface                          $executor
     * @param array<string, array{string, array<string, mixed>}> $queries
     *        Named parameter format: ['key' => ['SELECT...WHERE id = :id', ['id' => 1]]]
     */
    public function __construct(
        private readonly SqlBatchExecutorInterface $executor,
        private readonly array $queries,
    ) {
    }

    /**
     * Execute all queries and return results
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function __invoke(): array
    {
        if ($this->queries === []) {
            return [];
        }

        return $this->executor->execute($this->queries);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function isEmpty(): bool
    {
        return $this->queries === [];
    }

    public function count(): int
    {
        return count($this->queries);
    }
}
