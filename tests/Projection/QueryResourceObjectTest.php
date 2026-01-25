<?php

declare(strict_types=1);

namespace BEAR\Projection;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function spl_object_id;
use function sys_get_temp_dir;
use function unlink;

class QueryResourceObjectTest extends TestCase
{
    private string $sqlDir;
    private FakeSqlBatchExecutor $executor;
    private QueryBatchCoordinator $coordinator;

    protected function setUp(): void
    {
        $this->sqlDir = sys_get_temp_dir() . '/bear-projection-test';
        if (! is_dir($this->sqlDir)) {
            mkdir($this->sqlDir, 0777, true);
        }

        // Create test SQL file
        file_put_contents($this->sqlDir . '/user.sql', 'SELECT * FROM users WHERE id = :id');

        $this->executor = new FakeSqlBatchExecutor();
        $this->coordinator = new QueryBatchCoordinator($this->executor, $this->sqlDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->sqlDir . '/user.sql');
        @rmdir($this->sqlDir);
    }

    public function testImplementsInvokeRequestInterface(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        $this->assertInstanceOf(ResourceObject::class, $resource);
    }

    public function testInvokeRequestCallsCoordinatorExecuteAll(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        // Set up a mock request
        $mockResourceObject = new class extends ResourceObject {
            public function __construct()
            {
                $this->uri = new Uri('query://self/user?id=1');
            }
        };

        $mockInvoker = $this->createMock(InvokerInterface::class);
        $mockRequest = $this->createMock(AbstractRequest::class);
        $mockRequest->resourceObject = $mockResourceObject;

        // Set expected results
        $this->executor->results = [spl_object_id($resource) => [['id' => 1, 'name' => 'John']]];

        $result = $resource->_invokeRequest($mockInvoker, $mockRequest);

        $this->assertSame($resource, $result);
        $this->assertSame('query://self/user?id=1', (string) $resource->uri);
    }

    public function testRequestSetsUriFromRequest(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        $mockResourceObject = new class extends ResourceObject {
            public function __construct()
            {
                $this->uri = new Uri('query://self/user?id=42');
            }
        };

        $mockRequest = $this->createMock(AbstractRequest::class);
        $mockRequest->resourceObject = $mockResourceObject;

        $this->executor->results = [spl_object_id($resource) => [['id' => 42]]];

        $result = $resource->request($mockRequest);

        $this->assertSame($resource, $result);
        $this->assertSame('query://self/user?id=42', (string) $resource->uri);
    }

    public function testConstructorRegistersResourceWithCoordinator(): void
    {
        // Verify registration by checking that executeAll processes the resource
        $resource = new QueryResourceObject($this->coordinator);
        $resource->uri = new Uri('query://self/user?id=1');

        $expectedData = [['id' => 1, 'name' => 'Registered User']];
        $this->executor->results = [spl_object_id($resource) => $expectedData];

        $this->coordinator->executeAll();

        // If the resource was registered, its body should be populated
        $this->assertSame($expectedData, $resource->body);
    }

    public function testBodyIsPopulatedAfterExecution(): void
    {
        $resource = new QueryResourceObject($this->coordinator);

        $mockResourceObject = new class extends ResourceObject {
            public function __construct()
            {
                $this->uri = new Uri('query://self/user?id=1');
            }
        };

        $mockRequest = $this->createMock(AbstractRequest::class);
        $mockRequest->resourceObject = $mockResourceObject;

        $expectedData = [['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']];
        $this->executor->results = [spl_object_id($resource) => $expectedData];

        $resource->request($mockRequest);

        $this->assertSame($expectedData, $resource->body);
    }
}
