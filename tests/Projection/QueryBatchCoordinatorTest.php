<?php

declare(strict_types=1);

namespace BEAR\Projection;

use BEAR\Async\SqlBatchExecutorInterface;
use BEAR\Resource\Uri;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function spl_object_id;
use function sys_get_temp_dir;
use function unlink;

class QueryBatchCoordinatorTest extends TestCase
{
    private string $sqlDir;
    private FakeSqlBatchExecutor $executor;

    protected function setUp(): void
    {
        $this->sqlDir = sys_get_temp_dir() . '/bear-projection-test';
        if (! is_dir($this->sqlDir)) {
            mkdir($this->sqlDir, 0777, true);
        }

        $this->executor = new FakeSqlBatchExecutor();
    }

    protected function tearDown(): void
    {
        @unlink($this->sqlDir . '/user.sql');
        @unlink($this->sqlDir . '/posts.sql');
        @rmdir($this->sqlDir);
    }

    public function testRegisterAddsResourceToPool(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');

        $coordinator = new QueryBatchCoordinator($this->executor, $this->sqlDir);
        $resource = $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=1');

        $this->executor->results = [spl_object_id($resource) => [['id' => 1]]];

        $coordinator->executeAll();

        $this->assertArrayHasKey(spl_object_id($resource), $this->executor->executedQueries);
    }

    public function testExecuteAllBatchesMultipleQueries(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');
        file_put_contents($this->sqlDir . '/posts.sql', 'SELECT * FROM posts WHERE user_id = :user_id');

        $coordinator = new QueryBatchCoordinator($this->executor, $this->sqlDir);

        $resource1 = $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=1');
        $resource2 = $this->createQueryResourceWithUri($coordinator, 'query://self/posts?user_id=1');

        $this->executor->results = [
            spl_object_id($resource1) => [['id' => 1, 'name' => 'John']],
            spl_object_id($resource2) => [['id' => 10, 'title' => 'Post 1'], ['id' => 11, 'title' => 'Post 2']],
        ];

        $coordinator->executeAll();

        $this->assertCount(2, $this->executor->executedQueries);
        $this->assertArrayHasKey(spl_object_id($resource1), $this->executor->executedQueries);
        $this->assertArrayHasKey(spl_object_id($resource2), $this->executor->executedQueries);
    }

    public function testExecuteAllDistributesResultsToResources(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');
        file_put_contents($this->sqlDir . '/posts.sql', 'SELECT * FROM posts WHERE user_id = :user_id');

        $coordinator = new QueryBatchCoordinator($this->executor, $this->sqlDir);

        $resource1 = $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=1');
        $resource2 = $this->createQueryResourceWithUri($coordinator, 'query://self/posts?user_id=1');

        $expectedUser = [['id' => 1, 'name' => 'John']];
        $expectedPosts = [['id' => 10, 'title' => 'Post 1']];

        $this->executor->results = [
            spl_object_id($resource1) => $expectedUser,
            spl_object_id($resource2) => $expectedPosts,
        ];

        $coordinator->executeAll();

        $this->assertSame($expectedUser, $resource1->body);
        $this->assertSame($expectedPosts, $resource2->body);
    }

    public function testExecuteAllOnlyRunsOnce(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');

        $executionCount = 0;
        $countingExecutor = new class ($executionCount) implements SqlBatchExecutorInterface {
            public function __construct(
                private int &$count,
            ) {
            }

            public function execute(array $queries): array
            {
                $this->count++;

                return [];
            }
        };

        $coordinator = new QueryBatchCoordinator($countingExecutor, $this->sqlDir);
        $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=1');

        $coordinator->executeAll();
        $coordinator->executeAll();
        $coordinator->executeAll();

        $this->assertSame(1, $executionCount);
    }

    public function testExecuteAllWithEmptyResourcesDoesNothing(): void
    {
        $executionCount = 0;
        $countingExecutor = new class ($executionCount) implements SqlBatchExecutorInterface {
            public function __construct(
                private int &$count,
            ) {
            }

            public function execute(array $queries): array
            {
                $this->count++;

                return [];
            }
        };

        $coordinator = new QueryBatchCoordinator($countingExecutor, $this->sqlDir);

        $coordinator->executeAll();

        $this->assertSame(0, $executionCount);
    }

    public function testClearResetsState(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');

        $executionCount = 0;
        $countingExecutor = new class ($executionCount) implements SqlBatchExecutorInterface {
            public function __construct(
                private int &$count,
            ) {
            }

            public function execute(array $queries): array
            {
                $this->count++;

                return [];
            }
        };

        $coordinator = new QueryBatchCoordinator($countingExecutor, $this->sqlDir);
        $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=1');

        $coordinator->executeAll();
        $coordinator->clear();

        // After clear, should be able to register and execute again
        $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=2');
        $coordinator->executeAll();

        $this->assertSame(2, $executionCount);
    }

    public function testQueryParametersArePassedCorrectly(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id AND status = :status');

        $coordinator = new QueryBatchCoordinator($this->executor, $this->sqlDir);
        $resource = $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=42&status=active');

        $this->executor->results = [spl_object_id($resource) => []];

        $coordinator->executeAll();

        $query = $this->executor->executedQueries[spl_object_id($resource)];
        $this->assertSame('SELECT * FROM users WHERE id = :id AND status = :status', $query[0]);
        $this->assertSame(['id' => '42', 'status' => 'active'], $query[1]);
    }

    public function testMissingResultReturnsEmptyArray(): void
    {
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');

        $coordinator = new QueryBatchCoordinator($this->executor, $this->sqlDir);
        $resource = $this->createQueryResourceWithUri($coordinator, 'query://self/user?id=999');

        // Don't set any results - simulating a case where executor returns empty
        $this->executor->results = [];

        $coordinator->executeAll();

        $this->assertSame([], $resource->body);
    }

    private function createQueryResourceWithUri(QueryBatchCoordinator $coordinator, string $uriString): QueryResourceObject
    {
        $resource = new QueryResourceObject($coordinator);
        $resource->uri = new Uri($uriString);

        return $resource;
    }
}
