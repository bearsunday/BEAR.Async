<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MysqliParamBinderTest extends TestCase
{
    private MysqliParamBinder $binder;

    protected function setUp(): void
    {
        $this->binder = new MysqliParamBinder();
    }

    public function testConvertNamedToPositionalWithSingleParam(): void
    {
        $sql = 'SELECT * FROM users WHERE id = :id';
        $params = ['id' => 1];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame('SELECT * FROM users WHERE id = ?', $convertedSql);
        $this->assertSame([1], $orderedParams);
    }

    public function testConvertNamedToPositionalWithMultipleParams(): void
    {
        $sql = 'SELECT * FROM users WHERE name = :name AND age = :age';
        $params = ['name' => 'John', 'age' => 30];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame('SELECT * FROM users WHERE name = ? AND age = ?', $convertedSql);
        $this->assertSame(['John', 30], $orderedParams);
    }

    public function testConvertNamedToPositionalWithDuplicateParams(): void
    {
        $sql = 'SELECT * FROM users WHERE id = :id OR parent_id = :id';
        $params = ['id' => 5];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame('SELECT * FROM users WHERE id = ? OR parent_id = ?', $convertedSql);
        $this->assertSame([5, 5], $orderedParams);
    }

    public function testConvertNamedToPositionalWithNoParams(): void
    {
        $sql = 'SELECT * FROM users';
        $params = [];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame('SELECT * FROM users', $convertedSql);
        $this->assertSame([], $orderedParams);
    }

    public function testBuildTypeStringWithIntegers(): void
    {
        $params = [1, 2, 3];

        $types = $this->binder->buildTypeString($params);

        $this->assertSame('iii', $types);
    }

    public function testBuildTypeStringWithStrings(): void
    {
        $params = ['hello', 'world'];

        $types = $this->binder->buildTypeString($params);

        $this->assertSame('ss', $types);
    }

    public function testBuildTypeStringWithFloats(): void
    {
        $params = [1.5, 2.7];

        $types = $this->binder->buildTypeString($params);

        $this->assertSame('dd', $types);
    }

    public function testBuildTypeStringWithMixedTypes(): void
    {
        $params = [1, 'hello', 3.14, null];

        $types = $this->binder->buildTypeString($params);

        $this->assertSame('isds', $types);
    }

    public function testBuildTypeStringWithEmptyArray(): void
    {
        $params = [];

        $types = $this->binder->buildTypeString($params);

        $this->assertSame('', $types);
    }

    public function testBuildTypeStringWithBoolean(): void
    {
        $params = [true, false];

        $types = $this->binder->buildTypeString($params);

        // Booleans are treated as strings
        $this->assertSame('ss', $types);
    }

    public function testConvertNamedToPositionalPreservesQuotedStrings(): void
    {
        // Colons inside quoted strings should NOT be replaced
        $sql = "SELECT * FROM users WHERE created_at > '2024-01-01 10:00:00' AND id = :id";
        $params = ['id' => 1];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame("SELECT * FROM users WHERE created_at > '2024-01-01 10:00:00' AND id = ?", $convertedSql);
        $this->assertSame([1], $orderedParams);
    }

    public function testConvertNamedToPositionalPreservesDoubleQuotedStrings(): void
    {
        $sql = 'SELECT * FROM users WHERE url = "https://example.com" AND id = :id';
        $params = ['id' => 1];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame('SELECT * FROM users WHERE url = "https://example.com" AND id = ?', $convertedSql);
        $this->assertSame([1], $orderedParams);
    }

    public function testConvertNamedToPositionalWithEscapedQuotes(): void
    {
        $sql = "SELECT * FROM users WHERE name = 'O\\'Brien' AND id = :id";
        $params = ['id' => 1];

        [$convertedSql, $orderedParams] = $this->binder->convertNamedToPositional($sql, $params);

        $this->assertSame("SELECT * FROM users WHERE name = 'O\\'Brien' AND id = ?", $convertedSql);
        $this->assertSame([1], $orderedParams);
    }

    public function testConvertNamedToPositionalThrowsOnMissingParameter(): void
    {
        $sql = 'SELECT * FROM users WHERE id = :id AND name = :name';
        $params = ['id' => 1]; // Missing 'name' parameter

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing parameter: name');

        $this->binder->convertNamedToPositional($sql, $params);
    }
}
