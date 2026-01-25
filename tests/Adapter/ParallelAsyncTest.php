<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_get_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function str_contains;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @group parallel
 * @requires extension parallel
 */
class ParallelAsyncTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bear_async_test_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/vendor');
        // Create a minimal autoload.php for the bootstrap file
        file_put_contents($this->tempDir . '/vendor/autoload.php', '<?php');
    }

    protected function tearDown(): void
    {
        // Cleanup temp files
        if (file_exists($this->tempDir . '/vendor/autoload.php')) {
            unlink($this->tempDir . '/vendor/autoload.php');
        }

        if (is_dir($this->tempDir . '/vendor')) {
            rmdir($this->tempDir . '/vendor');
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testIsAvailable(): void
    {
        $async = new ParallelAsync(
            'Test\App',
            'test',
            $this->tempDir,
            2,
        );

        $this->assertTrue($async->isAvailable());
    }

    public function testBootstrapFileCreation(): void
    {
        $async = new ParallelAsync(
            'Test\App',
            'test-context',
            $this->tempDir,
            2,
        );

        // Get the bootstrap file path via reflection
        $reflection = new \ReflectionClass($async);
        $property = $reflection->getProperty('bootstrapFile');
        /** @var string $bootstrapFile */
        $bootstrapFile = $property->getValue($async);

        $this->assertFileExists($bootstrapFile);

        $content = file_get_contents($bootstrapFile);
        $this->assertIsString($content);
        $this->assertTrue(str_contains($content, 'Test\App'));
        $this->assertTrue(str_contains($content, 'test-context'));
        $this->assertTrue(str_contains($content, $this->tempDir));
        $this->assertTrue(str_contains($content, 'BEAR\Package\Injector'));
    }

    public function testInvokeWithEmptyTasks(): void
    {
        $async = new ParallelAsync(
            'Test\App',
            'test',
            $this->tempDir,
            2,
        );

        $this->expectNotToPerformAssertions();
        ($async)([]);
    }
}
