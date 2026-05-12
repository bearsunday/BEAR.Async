<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use ArrayObject;
use Closure;
use Ray\Aop\MethodInvocation;
use Ray\Aop\ReflectionMethod;

/**
 * @implements MethodInvocation<object>
 */
final class FakeMethodInvocation implements MethodInvocation
{
    public int $proceedCount = 0;

    /** @var list<mixed> */
    private readonly array $arguments;

    /**
     * @param list<mixed> $arguments
     */
    public function __construct(
        private readonly object $target,
        private readonly ReflectionMethod $method,
        public Closure|null $proceed = null,
        array $arguments = [],
    ) {
        $this->arguments = $arguments;
    }

    public function proceed(): mixed
    {
        $this->proceedCount++;

        if ($this->proceed === null) {
            return $this->target;
        }

        return ($this->proceed)();
    }

    public function getThis(): object
    {
        return $this->target;
    }

    /** @return ArrayObject<int, mixed> */
    public function getArguments(): ArrayObject
    {
        return new ArrayObject($this->arguments);
    }

    /** @return ArrayObject<non-empty-string, mixed> */
    public function getNamedArguments(): ArrayObject
    {
        return new ArrayObject([]);
    }

    public function getMethod(): ReflectionMethod
    {
        return $this->method;
    }
}
