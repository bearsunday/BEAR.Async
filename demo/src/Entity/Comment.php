<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class Comment
{
    public function __construct(
        public readonly int $id,
        public readonly string $author_name,
        public readonly string $body,
    ) {
    }
}
