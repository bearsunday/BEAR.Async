<?php

declare(strict_types=1);

namespace BEAR\Async\Projection;

use BEAR\Async\SqlBatchExecutorInterface;
use BEAR\Projection\QueryBatchCoordinator;
use BEAR\Projection\QueryResourceObject;
use BEAR\Resource\Uri;
use PHPUnit\Framework\TestCase;

class QueryBatchCoordinatorTest extends TestCase
{
    private string $sqlDir;

    protected function setUp(): void
    {
        $this->sqlDir = __DIR__ . '/sql';
        if (! is_dir($this->sqlDir)) {
            mkdir($this->sqlDir, 0777, true);
        }

        // Create test SQL files
        file_put_contents($this->sqlDir . '/users.sql', 'SELECT * FROM users WHERE id = :id');
        file_put_contents($this->sqlDir . '/posts.sql', 'SELECT * FROM posts WHERE user_id = :user_id');
    }

    protected function tearDown(): void
    {
        // Clean up test SQL files
        @unlink($this->sqlDir . '/users.sql');
        @unlink($this->sqlDir . '/posts.sql');
        @rmdir($this->sqlDir);
    }

    public function testRegisterAddsResource(): void
    {
        $executor = $this->createMockExecutor([]);
        $coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);

        $resource = $this->createQueryResource($coordinator);

        // Resource should be registered during construction
        $this->assertInstanceOf(QueryResourceObject::class, $resource);
    }

    public function testExecuteAllWithNoResources(): void
    {
        $executor = $this->createMockExecutor([]);
        $coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);

        // Should not throw when no resources registered
        $coordinator->executeAll();

        $this->assertTrue(true);
    }

    public function testExecuteAllExecutesOnce(): void
    {
        $callCount = 0;
        $executor = new class ($callCount) implements SqlBatchExecutorInterface {
            public function __construct(
                private int &$callCount,
            ) {
            }

            public function execute(array $queries): array
            {
                $this->callCount++;

                return [];
            }
        };

        $coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);
        $resource = $this->createQueryResourceWithUri($coordinator, '/users', ['id' => 1]);

        $coordinator->executeAll();
        $coordinator->executeAll(); // Second call should be no-op

        $this->assertSame(1, $callCount);
    }

    public function testExecuteAllSetsResultsToResourceBody(): void
    {
        $expectedResults = [
            ['id' => 1, 'name' => 'Test User'],
        ];

        $executor = new class ($expectedResults) implements SqlBatchExecutorInterface {
            /** @param list<array<string, mixed>> $results */
            public function __construct(
                private array $results,
            ) {
            }

            public function execute(array $queries): array
            {
                $result = [];
                foreach ($queries as $key => $_) {
                    $result[$key] = $this->results;
                }

                return $result;
            }
        };

        $coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);
        $resource = $this->createQueryResourceWithUri($coordinator, '/users', ['id' => 1]);

        $coordinator->executeAll();

        $this->assertSame($expectedResults, $resource->body);
    }

    public function testClearResetsState(): void
    {
        $callCount = 0;
        $executor = new class ($callCount) implements SqlBatchExecutorInterface {
            public function __construct(
                private int &$callCount,
            ) {
            }

            public function execute(array $queries): array
            {
                $this->callCount++;

                return [];
            }
        };

        $coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);
        $resource = $this->createQueryResourceWithUri($coordinator, '/users', ['id' => 1]);

        $coordinator->executeAll();
        $coordinator->clear();

        // After clear, executeAll should be no-op (no resources registered)
        $coordinator->executeAll();

        $this->assertSame(1, $callCount);
    }

    public function testMultipleResourcesExecutedTogether(): void
    {
        $executor = new class implements SqlBatchExecutorInterface {
            public int $queryCount = 0;

            public function execute(array $queries): array
            {
                $this->queryCount = count($queries);
                $result = [];
                foreach ($queries as $key => $_) {
                    $result[$key] = [['data' => $key]];
                }

                return $result;
            }
        };

        $coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);
        $resource1 = $this->createQueryResourceWithUri($coordinator, '/users', ['id' => 1]);
        $resource2 = $this->createQueryResourceWithUri($coordinator, '/posts', ['user_id' => 1]);

        $coordinator->executeAll();

        $this->assertSame(2, $executor->queryCount);
    }

    /** @param array<string, list<array<string, mixed>>> $results */
    private function createMockExecutor(array $results): SqlBatchExecutorInterface
    {
        return new class ($results) implements SqlBatchExecutorInterface {
            /** @param array<string, list<array<string, mixed>>> $results */
            public function __construct(
                private array $results,
            ) {
            }

            public function execute(array $queries): array
            {
                return $this->results;
            }
        };
    }

    private function createQueryResource(QueryBatchCoordinator $coordinator): QueryResourceObject
    {
        return new QueryResourceObject($coordinator);
    }

    /** @param array<string, mixed> $query */
    private function createQueryResourceWithUri(
        QueryBatchCoordinator $coordinator,
        string $path,
        array $query,
    ): QueryResourceObject {
        $resource = new QueryResourceObject($coordinator);
        $resource->uri = new Uri('app://self' . $path, $query);

        return $resource;
    }
}
