<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\Di\Di\Named;
use Swoole\Coroutine;

use function class_exists;
use function extension_loaded;
use function file_put_contents;
use function getmypid;
use function microtime;
use function sprintf;
use function usleep;

use const FILE_APPEND;

/**
 * Interceptor that adds artificial delay to simulate realistic SQL execution time
 *
 * Uses Swoole\Coroutine::sleep() for coroutine-friendly sleeping.
 * Falls back to usleep() when not in a coroutine context or when Swoole is not available.
 */
final class SlowQueryInterceptor implements MethodInterceptor
{
    public function __construct(
        #[Named('slow_query_delay_ms')] private readonly int $delayMs,
    ) {
    }

    public function invoke(MethodInvocation $invocation): mixed
    {
        $start = microtime(true);
        $class = $invocation->getMethod()->getDeclaringClass()->getShortName();
        file_put_contents('/tmp/parallel-debug.log', sprintf("[%.3f] START %s pid=%d\n", $start, $class, getmypid()), FILE_APPEND);

        // Add delay before executing the actual query
        $this->sleep();

        $result = $invocation->proceed();

        $end = microtime(true);
        file_put_contents('/tmp/parallel-debug.log', sprintf("[%.3f] END   %s pid=%d (%.0fms)\n", $end, $class, getmypid(), ($end - $start) * 1000), FILE_APPEND);

        return $result;
    }

    private function sleep(): void
    {
        // Check if Swoole is available and we're in a coroutine
        if (extension_loaded('swoole') && class_exists(Coroutine::class) && Coroutine::getCid() !== -1) {
            Coroutine::sleep($this->delayMs / 1000);

            return;
        }

        // Fallback to regular usleep
        usleep($this->delayMs * 1000);
    }
}
