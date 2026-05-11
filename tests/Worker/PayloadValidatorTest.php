<?php

declare(strict_types=1);

namespace BEAR\Async\Worker;

use BEAR\Async\Exception\NonCopyablePayloadException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function fopen;

class PayloadValidatorTest extends TestCase
{
    /** @return iterable<string, array{0: mixed}> */
    public static function copyableValues(): iterable
    {
        yield 'null' => [null];
        yield 'true' => [true];
        yield 'false' => [false];
        yield 'int' => [42];
        yield 'float' => [3.14];
        yield 'string' => ['hello'];
        yield 'empty array' => [[]];
        yield 'list of scalars' => [[1, 2, 3]];
        yield 'assoc array' => [['a' => 1, 'b' => 'two']];
        yield 'nested array' => [['a' => ['b' => ['c' => null]]]];
    }

    #[DataProvider('copyableValues')]
    public function testAcceptsCopyableValues(mixed $value): void
    {
        $this->expectNotToPerformAssertions();
        PayloadValidator::assertCopyable($value);
    }

    public function testRejectsObject(): void
    {
        $this->expectException(NonCopyablePayloadException::class);
        $this->expectExceptionMessage('Non-copyable value at $: stdClass');
        PayloadValidator::assertCopyable(new stdClass());
    }

    public function testRejectsResource(): void
    {
        $resource = fopen('php://memory', 'r');
        $this->expectException(NonCopyablePayloadException::class);
        try {
            PayloadValidator::assertCopyable($resource);
        } finally {
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function testRejectsClosure(): void
    {
        $this->expectException(NonCopyablePayloadException::class);
        $this->expectExceptionMessage('Non-copyable value at $: Closure');
        PayloadValidator::assertCopyable(static fn () => null);
    }

    public function testReportsPathForNestedObject(): void
    {
        $this->expectException(NonCopyablePayloadException::class);
        $this->expectExceptionMessage("Non-copyable value at \$['user']['profile'][0]: stdClass");
        PayloadValidator::assertCopyable([
            'user' => [
                'profile' => [new stdClass()],
            ],
        ]);
    }
}
