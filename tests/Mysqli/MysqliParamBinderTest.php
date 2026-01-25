<?php

declare(strict_types=1);

namespace BEAR\Async\Mysqli;

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
}
