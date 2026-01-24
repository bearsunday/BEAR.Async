<?php

declare(strict_types=1);

namespace BEAR\Async\Demo\Resource\App\Slow;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\ResourceObject;

/**
 * Slow Dashboard - 10 parallel embeds with simulated I/O delay
 *
 * Each embedded resource has 5ms delay (simulating DB query).
 * Uses AsyncEmbedModule for parallel execution with BEAR.Async.
 *
 * Sequential: 10 * 5ms = 50ms
 * Parallel:   ~5ms (longest single query)
 * Expected speedup: ~10x
 */
class Dashboard extends ResourceObject
{
    #[Embed(rel: 'users', src: 'app://self/slow/users')]
    #[Embed(rel: 'posts', src: 'app://self/slow/posts')]
    #[Embed(rel: 'comments', src: 'app://self/slow/comments')]
    #[Embed(rel: 'tags', src: 'app://self/slow/tags')]
    #[Embed(rel: 'categories', src: 'app://self/slow/categories')]
    #[Embed(rel: 'notifications', src: 'app://self/slow/notifications')]
    #[Embed(rel: 'analytics', src: 'app://self/slow/analytics')]
    #[Embed(rel: 'settings', src: 'app://self/slow/settings')]
    #[Embed(rel: 'profile', src: 'app://self/slow/profile')]
    #[Embed(rel: 'activity', src: 'app://self/slow/activity')]
    public function onGet(): static
    {
        $this->body += [
            'message' => 'Dashboard with 10 embedded resources',
            'embed_count' => 10,
            'delay_per_embed_ms' => 5,
        ];

        return $this;
    }
}
