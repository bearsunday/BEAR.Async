<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class Activity
{
    public function __construct(
        public readonly int $id,
        public readonly string $action,
        public readonly string $target,
        public readonly string $created_at,
    ) {
    }
}
