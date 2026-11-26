<?php

declare(strict_types=1);

namespace BEAR\Async\Fake\SafeEmbed\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;
use LogicException;

final class Guarded extends ResourceObject
{
    #[Link(rel: 'doTouch', href: 'app://self/guarded', method: 'post')]
    public function onGet(): static
    {
        $this->body = ['touched' => false];

        return $this;
    }

    public function onPost(): static
    {
        throw new LogicException('An embed ran the POST link of its src.');
    }
}
