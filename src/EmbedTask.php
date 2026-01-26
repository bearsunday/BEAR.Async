<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;

/**
 * Represents a single embed task for parallel execution
 *
 * Used by AsyncRenderDecorator to collect embedded requests and
 * execute them in parallel before rendering.
 *
 * @psalm-import-type Body from \BEAR\Resource\Types
 */
final class EmbedTask
{
    /** @var Body|null */
    private array|null $result = null;

    public function __construct(
        private readonly AbstractRequest $request,
    ) {
    }

    public function getRequest(): AbstractRequest
    {
        return $this->request;
    }

    /**
     * Set the result body from parallel execution
     *
     * @param Body|null $result The result body from the request
     */
    public function setResult(array|null $result): void
    {
        $this->result = $result;
    }

    /** @return Body|null */
    public function getResult(): array|null
    {
        return $this->result;
    }
}
