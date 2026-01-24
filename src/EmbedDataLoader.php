<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\Code;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\Request;
use Throwable;

use function assert;

/**
 * EmbedDataLoader loads collected embed requests in parallel using AsyncInterface
 *
 * This loader takes futures from EmbedRequests and executes them
 * concurrently using the configured AsyncInterface implementation.
 *
 * @see https://github.com/graphql/dataloader DataLoader pattern
 */
final class EmbedDataLoader
{
    public function __construct(
        private readonly AsyncInterface $async,
    ) {
    }

    /**
     * Load all pending futures in parallel
     *
     * Uses AsyncInterface for concurrent execution.
     * Falls back to sequential execution if async is not available.
     */
    public function load(EmbedRequests $requests): void
    {
        $futures = $requests->drain();
        if ($futures === []) {
            return;
        }

        // Check if async is available
        if (! $this->async->isAvailable()) {
            $this->loadSequentially($futures);

            return;
        }

        $this->loadParallel($futures);
    }

    /** @param array<string, FutureResource> $futures */
    private function loadParallel(array $futures): void
    {
        // Convert FutureResource to RequestTask
        $tasks = [];
        foreach ($futures as $id => $future) {
            $request = $future->getRequest();
            assert($request instanceof Request);
            $tasks[$id] = new RequestTask($id, $request);
        }

        // Execute via AsyncInterface
        ($this->async)($tasks);

        // Resolve futures with results
        foreach ($tasks as $id => $task) {
            $result = $task->getResult();
            $request = $task->getRequest();

            // Create ResourceObject from result
            $ro = new NullResourceObject();
            $ro->body = $result;
            $ro->uri = $request->resourceObject->uri;

            $futures[$id]->resolve($ro);
        }
    }

    /** @param array<string, FutureResource> $futures */
    private function loadSequentially(array $futures): void
    {
        foreach ($futures as $future) {
            try {
                $result = ($future->getRequest())();
                $future->resolve($result);
            } catch (Throwable $e) {
                $future->resolve($this->createErrorResource($e));
            }
        }
    }

    /** Create an error resource for failed requests */
    private function createErrorResource(Throwable $e): NullResourceObject
    {
        $resource = new NullResourceObject();
        $resource->code = Code::ERROR;
        $resource->body = [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ];

        return $resource;
    }
}
