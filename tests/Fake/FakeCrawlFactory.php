<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\FactoryInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Uri;

/**
 * Routes a URI path to a resource class, so a test declares its link graph as classes
 */
final class FakeCrawlFactory implements FactoryInterface
{
    /** @param array<string, class-string<ResourceObject>> $routes URI path => resource class */
    public function __construct(
        private readonly array $routes,
    ) {
    }

    public function newInstance($uri): ResourceObject
    {
        $uri = new Uri((string) $uri);
        $class = $this->routes[$uri->path];
        $ro = new $class();
        $ro->uri = $uri;

        return $ro;
    }
}
