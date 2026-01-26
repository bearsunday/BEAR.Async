<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use Override;
use Ray\Di\Di\Named;
use ReflectionProperty;

use function is_array;

/**
 * Decorator that pre-executes embedded requests in parallel before rendering
 *
 * This decorator collects all AbstractRequest objects from the resource body
 * and executes them in parallel using AsyncInterface. The results are cached
 * in the requests, so when the actual renderer processes them, it uses the
 * cached results instead of executing them again.
 *
 * Key insight: AbstractRequest has built-in caching:
 * - $result: cached after invoke()
 * - $view: cached after render() (in ResourceObject)
 *
 * By triggering execution before rendering, both caches are populated,
 * and the standard renderer can work unchanged.
 */
final class AsyncRenderDecorator implements RenderInterface
{
    private readonly ReflectionProperty $resultProperty;

    public function __construct(
        #[Named('async.inner')] private readonly RenderInterface $inner,
        private readonly AsyncInterface $async,
    ) {
        $this->resultProperty = new ReflectionProperty(AbstractRequest::class, 'result');
    }

    /** {@inheritDoc} */
    #[Override]
    public function render(ResourceObject $ro): string
    {
        // Collect all AbstractRequests from body
        $requests = $this->collectRequests($ro->body);

        // Execute in parallel: triggers invoke + render, both cached
        if ($requests !== [] && $this->async->isAvailable()) {
            $this->executeParallel($requests);
        }

        // Delegate to actual renderer (uses cached results)
        return $this->inner->render($ro);
    }

    /** @return list<AbstractRequest> */
    private function collectRequests(mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        $requests = [];
        /** @var mixed $value */
        foreach ($body as $value) {
            if ($value instanceof AbstractRequest) {
                $requests[] = $value;
            }
        }

        return $requests;
    }

    /** @param list<AbstractRequest> $requests */
    private function executeParallel(array $requests): void
    {
        // Convert to tasks for AsyncInterface
        $tasks = [];
        foreach ($requests as $i => $request) {
            $tasks["embed_{$i}"] = new EmbedTask($request);
        }

        ($this->async)($tasks);

        // For adapters that don't populate cache directly (like ParallelAsync),
        // we need to populate it here using the task results
        foreach ($tasks as $task) {
            $this->ensureCachePopulated($task);
        }
    }

    /**
     * Ensure the request's result cache is populated
     *
     * For Swoole/Sync: cache is already populated by (string) call
     * For Parallel: cache is not populated, so we use the task result
     */
    private function ensureCachePopulated(EmbedTask $task): void
    {
        $request = $task->getRequest();

        // Check if cache is already populated (Swoole/Sync do this directly)
        if ($this->resultProperty->getValue($request) !== null) {
            return;
        }

        // Cache not populated (Parallel), use task result
        $body = $task->getResult();
        if ($body === null) {
            return;
        }

        // Create ResourceObject with the body and set as cached result
        $ro = new NullResourceObject();
        $ro->body = $body;
        $ro->uri = $request->resourceObject->uri;

        $this->resultProperty->setValue($request, $ro);
    }
}
