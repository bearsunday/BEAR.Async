<?php

declare(strict_types=1);

namespace BEAR\Async;

use PHPUnit\Framework\TestCase;

class SqlBatchTest extends TestCase
{
    public function testConstructorWithEmptyArray(): void
    {
        $batch = new SqlBatch([]);

        $this->assertTrue($batch->isEmpty());
        $this->assertSame(0, $batch->count());
        $this->assertSame([], $batch->getQueries());
    }

    public function testConstructorWithQueries(): void
    {
        $queries = [
            'users' => ['SELECT * FROM users WHERE id = :id', ['id' => 1]],
            'posts' => ['SELECT * FROM posts WHERE user_id = :user_id', ['user_id' => 1]],
        ];

        $batch = new SqlBatch($queries);

        $this->assertFalse($batch->isEmpty());
        $this->assertSame(2, $batch->count());
        $this->assertSame($queries, $batch->getQueries());
    }

    public function testGetQueriesReturnsAllQueries(): void
    {
        $queries = [
            'query1' => ['SELECT 1', []],
            'query2' => ['SELECT 2', []],
            'query3' => ['SELECT 3', []],
        ];

        $batch = new SqlBatch($queries);

        $this->assertSame($queries, $batch->getQueries());
    }

    public function testIsEmptyReturnsTrueForEmptyBatch(): void
    {
        $batch = new SqlBatch([]);

        $this->assertTrue($batch->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyBatch(): void
    {
        $batch = new SqlBatch([
            'test' => ['SELECT 1', []],
        ]);

        $this->assertFalse($batch->isEmpty());
    }

    public function testCountReturnsCorrectNumber(): void
    {
        $batch1 = new SqlBatch([]);
        $this->assertSame(0, $batch1->count());

        $batch2 = new SqlBatch([
            'one' => ['SELECT 1', []],
        ]);
        $this->assertSame(1, $batch2->count());

        $batch3 = new SqlBatch([
            'one' => ['SELECT 1', []],
            'two' => ['SELECT 2', []],
            'three' => ['SELECT 3', []],
        ]);
        $this->assertSame(3, $batch3->count());
    }

    public function testQueriesWithNamedParameters(): void
    {
        $queries = [
            'user' => ['SELECT * FROM users WHERE email = :email AND status = :status', ['email' => 'test@example.com', 'status' => 'active']],
        ];

        $batch = new SqlBatch($queries);
        $retrievedQueries = $batch->getQueries();

        $this->assertSame(['email' => 'test@example.com', 'status' => 'active'], $retrievedQueries['user'][1]);
    }
}
