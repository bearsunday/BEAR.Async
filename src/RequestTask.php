<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\Request;

/**
 * Represents a single request task that may be shared by multiple targets
 *
 * When the same resource is requested multiple times (e.g., same user's posts
 * from different contexts), we deduplicate by using the request hash.
 * Multiple targets can reference the same task, and when the result is set,
 * all targets are updated.
 *
 * @psalm-import-type Body from \BEAR\Resource\Types
 */
final class RequestTask
{
    /** @var Body|null */
    private array|null $result = null;

    /** @var list<array{body: array<string, mixed>, rel: string}> */
    private array $targets = [];

    public function __construct(
        private readonly string $hash,
        private readonly Request $request,
    ) {
    }

    /**
     * Add a target body array that will receive this task's result
     *
     * @param array<string, mixed> $body The body array to update (passed by reference)
     * @param string               $rel  The relation key to set the result under
     */
    public function addTarget(array &$body, string $rel): void
    {
        $this->targets[] = ['body' => &$body, 'rel' => $rel];
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    /**
     * Set the result and propagate to all registered targets
     *
     * @param Body|null $result The result body from the request
     */
    public function setResult(array|null $result): void
    {
        $this->result = $result;
        foreach ($this->targets as &$target) {
            /** @var array<string, mixed> $body */
            $body = &$target['body'];
            $body[$target['rel']] = $result;
        }
    }

    /** @return Body|null */
    public function getResult(): array|null
    {
        return $this->result;
    }

    /** @return list<array{body: array<string, mixed>, rel: string}> */
    public function getTargets(): array
    {
        return $this->targets;
    }
}
