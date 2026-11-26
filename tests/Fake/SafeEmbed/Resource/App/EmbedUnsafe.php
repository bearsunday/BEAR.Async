<?php

declare(strict_types=1);

namespace BEAR\Async\Fake\SafeEmbed\Resource\App;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\ResourceObject;

class EmbedUnsafe extends ResourceObject
{
    #[Embed(rel: 'item', src: 'app://self/guarded#link=self:doTouch')]
    public function onGet(): static
    {
        return $this;
    }
}
