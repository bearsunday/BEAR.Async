<?php

declare(strict_types=1);

namespace BEAR\Async;

use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Swoole\Database\PDOProxy;

#[RequiresPhpExtension('swoole')]
final class PdoProxyExtractorTest extends TestCase
{
    public function testExtractReturnsWrappedPdo(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $proxy = new PDOProxy(static fn (): PDO => $pdo);

        $this->assertSame($pdo, PdoProxyExtractor::extract($proxy));
        // Second call reads through the cached ReflectionProperty.
        $this->assertSame($pdo, PdoProxyExtractor::extract($proxy));
    }
}
