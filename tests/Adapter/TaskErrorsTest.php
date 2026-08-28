<?php

declare(strict_types=1);

namespace BEAR\Async\Adapter;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TaskErrorsTest extends TestCase
{
    public function testThrowFirstFollowsKeyOrderNotInsertionOrder(): void
    {
        $errors = new TaskErrors();
        $laterTaskError = new RuntimeException('later task, finished first');
        $earlierTaskError = new RuntimeException('earlier task, finished last');
        $errors->add('b', $laterTaskError);
        $errors->add('a', $earlierTaskError);

        try {
            $errors->throwFirst(['a', 'b']);
            $this->fail('throwFirst() did not throw');
        } catch (RuntimeException $e) {
            $this->assertSame($earlierTaskError, $e);
        }
    }

    public function testThrowFirstWithoutErrorsDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();

        (new TaskErrors())->throwFirst(['a', 'b']);
    }

    public function testHas(): void
    {
        $errors = new TaskErrors();

        $this->assertFalse($errors->has('a'));

        $errors->add('a', new RuntimeException('failed'));

        $this->assertTrue($errors->has('a'));
        $this->assertFalse($errors->has('b'));
    }
}
