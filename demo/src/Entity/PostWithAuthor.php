<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class PostWithAuthor
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $author_name,
        public readonly ?int $view_count = null,
        public readonly ?string $created_at = null,
    ) {
    }
}
