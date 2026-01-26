<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class Notification
{
    public function __construct(
        public readonly int $id,
        public readonly string $message,
        public readonly string|null $read_at,
        public readonly string $created_at,
    ) {
    }
}
