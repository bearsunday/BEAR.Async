<?php

declare(strict_types=1);

namespace BEAR\Async;

/**
 * Immutable value object representing a batch of SQL queries
 *
 * Each query is identified by a unique key and contains:
 * - SQL string with named parameters (e.g., :id, :name)
 * - Parameter values as associative array
 *
 * Usage:
 *   $batch = new SqlBatch([
 *       'users' => ['SELECT * FROM users WHERE id = :id', ['id' => 1]],
 *       'posts' => ['SELECT * FROM posts WHERE user_id = :user_id', ['user_id' => 1]],
 *   ]);
 */
final class SqlBatch
{
    /**
     * @param array<string, array{string, array<string, mixed>}> $queries
     *        Named parameter format: ['key' => ['SELECT...WHERE id = :id', ['id' => 1]]]
     */
    public function __construct(
        private readonly array $queries,
    ) {
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
