<?php

declare(strict_types=1);

namespace BEAR\Async\Projection;

use BEAR\Async\SqlBatchExecutorInterface;
use BEAR\Projection\QueryBatchCoordinator;
use BEAR\Projection\QueryResourceObject;
use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use PHPUnit\Framework\TestCase;

class QueryResourceObjectTest extends TestCase
{
    private string $sqlDir;
    private QueryBatchCoordinator $coordinator;

    protected function setUp(): void
    {
        $this->sqlDir = __DIR__ . '/sql';
        if (! is_dir($this->sqlDir)) {
            mkdir($this->sqlDir, 0777, true);
        }

        file_put_contents($this->sqlDir . '/users.sql', 'SELECT * FROM users WHERE id = :id');

        $executor = new class implements SqlBatchExecutorInterface {
            public function execute(array $queries): array
            {
                $result = [];
                foreach ($queries as $key => $_) {
                    $result[$key] = [['id' => 1, 'name' => 'Test User']];
                }

                return $result;
            }
        };

        $this->coordinator = new QueryBatchCoordinator($executor, $this->sqlDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->sqlDir . '/users.sql');
        @rmdir($this->sqlDir);
    }

    public function testConstructorRegistersToCoordinator(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        $this->assertInstanceOf(QueryResourceObject::class, $resource);
    }

    public function testInvokeRequestExecutesBatch(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        $requestRo = new class extends ResourceObject {
        };
        $requestRo->uri = new Uri('app://self/users', ['id' => 1]);

        $invoker = $this->createMock(InvokerInterface::class);
        $request = new Request(
            $invoker,
            $requestRo,
        );

        $result = $resource->_invokeRequest($invoker, $request);

        $this->assertSame($resource, $result);
        $this->assertSame([['id' => 1, 'name' => 'Test User']], $resource->body);
    }

    public function testRequestSetsUriFromRequest(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        $requestRo = new class extends ResourceObject {
        };
        $requestRo->uri = new Uri('app://self/users', ['id' => 42]);

        $invoker = $this->createMock(InvokerInterface::class);
        $request = new Request(
            $invoker,
            $requestRo,
        );

        $resource->request($request);

        $this->assertSame('app://self/users?id=42', (string) $resource->uri);
        $this->assertSame(['id' => 42], $resource->uri->query);
    }
}
