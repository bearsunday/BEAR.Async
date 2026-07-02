<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\ResourceInterface;
use Ray\Di\ProviderInterface;

/**
 * Fake ProviderInterface<ResourceInterface> for DeferredRequest tests.
 *
 * @implements ProviderInterface<ResourceInterface>
 */
final class FakePendingResourceProvider implements ProviderInterface
{
    public function __construct(
        private readonly ResourceInterface $resource,
    ) {
    }

    public function get(): ResourceInterface
    {
        return $this->resource;
    }
}
