<?php

declare(strict_types=1);

namespace BEAR\Async;

enum Adapter
{
    case Swoole;   // ext-swoole + coroutine context
    case Amp;      // amphp/amp
    case Sync;     // synchronous (for testing/development)
}
