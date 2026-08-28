<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use BEAR\AppMeta\AbstractAppMeta;
use BEAR\Async\Worker\WorkerResourceCache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_dir;
use function mkdir;
use function rmdir;
use function str_ends_with;
use function sys_get_temp_dir;
use function uniqid;

#[Group('parallel')]
#[RequiresPhpExtension('parallel')]
class ParallelAsyncTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bear_async_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        WorkerResourceCache::reset();
    }

    public function testInvokeWithEmptyTasks(): void
    {
        $async = new ParallelAsync($this->makeMeta(), 'test-context', 2);

        $this->expectNotToPerformAssertions();
        ($async)([]);
    }

    public function testExecuteWithEmptyRequestsReturnsEmptyArray(): void
    {
        $async = new ParallelAsync($this->makeMeta(), 'test-context', 2);

        $this->assertSame([], $async->execute([]));
    }

    public function testBootstrapFileExists(): void
    {
        $async = new ParallelAsync($this->makeMeta(), 'test-context', 2);

        $reflection = new \ReflectionClass($async);
        $property = $reflection->getProperty('bootstrapFile');
        /** @var string $bootstrapFile */
        $bootstrapFile = $property->getValue($async);

        $this->assertFileExists($bootstrapFile);
        $this->assertTrue(str_ends_with($bootstrapFile, 'worker-bootstrap.php'));
    }

    private function makeMeta(): AbstractAppMeta
    {
        $appDir = $this->tempDir;
        assert($appDir !== '');

        return new class ($appDir) extends AbstractAppMeta {
            /** @param non-empty-string $appDir */
            public function __construct(string $appDir)
            {
                $this->name = 'Test\App';
                $this->appDir = $appDir;
                $this->tmpDir = $appDir;
                $this->logDir = $appDir;
            }
        };
    }
}
