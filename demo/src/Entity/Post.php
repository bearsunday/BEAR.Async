<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class Post
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $body,
        public readonly int $view_count,
    ) {
    }
}
