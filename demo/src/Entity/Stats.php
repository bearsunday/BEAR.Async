<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Entity;

final class Stats
{
    public function __construct(
        public readonly int $total_authors,
        public readonly int $total_posts,
        public readonly int $total_comments,
        public readonly int $total_views,
    ) {
    }
}
