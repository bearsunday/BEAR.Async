<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\ResourceObject;

final class AsyncEmbedResource extends ResourceObject
{
    #[Embed(rel: 'embedded', src: 'app://self/embedded')]
    public function onGet(): static
    {
        return $this;
    }

    #[Embed(rel: 'user', src: 'app://self/user')]
    #[Embed(rel: 'posts', src: 'app://self/posts')]
    public function withMultipleEmbeds(): static
    {
        return $this;
    }

    public function withoutEmbed(): static
    {
        return $this;
    }
}
