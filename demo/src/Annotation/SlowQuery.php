<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Annotation;

use Attribute;

/**
 * Marks a resource method to simulate slow query execution
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class SlowQuery
{
    public function __construct(
        public readonly int $delayMs = 10,
    ) {
    }
}
