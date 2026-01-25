<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $post_count,
    ) {
    }
}
