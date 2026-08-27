<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceObject;
use Override;

use function json_decode;
use function md5;
use function serialize;

use const JSON_THROW_ON_ERROR;

/**
 * Decorator for AbstractRequest that enables parallel execution
 *
 * When rendered to string, requests the result from PendingRequests which
 * triggers parallel execution of all pending requests at once.
 *
 * Extends AbstractRequest so that callers performing `instanceof AbstractRequest`
 * checks (e.g. BEAR\Resource\HalRenderer::valuateElements,
 * BEAR\QueryRepository\ResourceDonut) recognise an AsyncRequest as a
 * renderable request rather than silently skipping it. The actual flush of
 * pending requests happens when a renderer (or any caller) casts the embed
 * to string, which dispatches __toString() below.
 *
 * This is the "そうめん" (somen) that gets queued in PendingRequests and
 * flows through together when any result is requested.
 */
final class AsyncRequest extends AbstractRequest
{
    public function __construct(
        private readonly AbstractRequest $inner,
        private readonly PendingRequests $pendingRequests,
    ) {
        parent::__construct(
            $inner->invoker,
            $inner->resourceObject,
            $inner->method,
            $inner->query,
            $inner->links,
            null,
        );
        // The parent's $uri is an uninitialised public field, kept around for
        // backward compatibility with callers that read $request->uri directly.
        $this->uri = $inner->toUri();
        $pendingRequests->add($this);
    }

    /**
     * Invoke the inner request directly and record the result
     *
     * This path is hit not only by explicit `$request()` calls but by every
     * inherited accessor that memoizes via AbstractRequest::invoke()
     * (__get, offsetGet, offsetExists, getIterator) — and by the adapters
     * themselves, whose execute() renders each request via `(string) $request()`.
     * The result is handed to PendingRequests::complete() so the pending
     * batch does not execute this request a second time, and a later
     * __toString()/jsonSerialize() can render from the already-invoked
     * ResourceObject.
     */
    #[Override]
    public function __invoke(array|null $query = null): ResourceObject
    {
        $previousKey = $this->hash();
        $ro = ($this->inner)($query);
        $this->query = $this->inner->query;
        $this->uri = $this->inner->toUri();
        $this->pendingRequests->rekey($previousKey, $this);
        $this->pendingRequests->complete($this, $ro);

        return $ro;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->pendingRequests->getResult($this);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        $view = $this->pendingRequests->getResult($this);

        return json_decode($view, true, 512, JSON_THROW_ON_ERROR);
    }

    #[Override]
    public function toUri(): string
    {
        return $this->inner->toUri();
    }

    #[Override]
    public function toUriWithMethod(): string
    {
        return $this->inner->toUriWithMethod();
    }

    /**
     * Identity is method + URI + links, computed uniformly here
     *
     * PendingRequests keys pending/results by this hash. The inherited
     * class-based hash() cannot be used: for deferred requests the
     * $resourceObject is a NullResourceObject shared across different URIs,
     * and for plain Requests it ignores the URI entirely, so two embeds of
     * the same resource class pointing at different URIs would collide and
     * silently share one result. The formula matches DeferredRequest::hash().
     */
    #[Override]
    public function hash(): string
    {
        return md5($this->inner->method->value . $this->inner->toUri() . serialize($this->inner->links));
    }

    /** {@inheritDoc} */
    #[Override]
    public function withQuery(array $query): RequestInterface
    {
        $previousKey = $this->hash();
        $this->inner->withQuery($query);
        $this->query = $this->inner->query;
        $this->uri = $this->inner->toUri();
        $this->pendingRequests->rekey($previousKey, $this);

        return $this;
    }

    /** {@inheritDoc} */
    #[Override]
    public function addQuery(array $query): RequestInterface
    {
        $previousKey = $this->hash();
        $this->inner->addQuery($query);
        $this->query = $this->inner->query;
        $this->uri = $this->inner->toUri();
        $this->pendingRequests->rekey($previousKey, $this);

        return $this;
    }

    #[Override]
    public function linkSelf(string $linkKey): RequestInterface
    {
        $previousKey = $this->hash();
        $this->inner->linkSelf($linkKey);
        $this->links = $this->inner->links;
        $this->pendingRequests->rekey($previousKey, $this);

        return $this;
    }

    #[Override]
    public function linkNew(string $linkKey): RequestInterface
    {
        $previousKey = $this->hash();
        $this->inner->linkNew($linkKey);
        $this->links = $this->inner->links;
        $this->pendingRequests->rekey($previousKey, $this);

        return $this;
    }

    #[Override]
    public function linkCrawl(string $linkKey): RequestInterface
    {
        $previousKey = $this->hash();
        $this->inner->linkCrawl($linkKey);
        $this->links = $this->inner->links;
        $this->pendingRequests->rekey($previousKey, $this);

        return $this;
    }
}
