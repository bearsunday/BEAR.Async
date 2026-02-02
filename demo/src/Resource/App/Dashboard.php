<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Resource\App;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\ResourceObject;

/**
 * Dashboard resource - embed root with 8 parallel embeds
 *
 * Demonstrates flat parallel execution of independent resources
 */
class Dashboard extends ResourceObject
{
    #[Embed(rel: 'profile', src: 'app://self/user/profile?id={user_id}')]
    #[Embed(rel: 'notifications', src: 'app://self/user/notifications?user_id={user_id}')]
    #[Embed(rel: 'recent_posts', src: 'app://self/posts/recent')]
    #[Embed(rel: 'popular_posts', src: 'app://self/posts/popular')]
    #[Embed(rel: 'stats', src: 'app://self/stats')]
    #[Embed(rel: 'categories', src: 'app://self/categories')]
    #[Embed(rel: 'tags_cloud', src: 'app://self/tags/cloud')]
    #[Embed(rel: 'activity', src: 'app://self/activity/recent?user_id={user_id}')]
    public function onGet(int $user_id = 1): static
    {
        $this->body['user_id'] = $user_id;

        return $this;
    }
}
