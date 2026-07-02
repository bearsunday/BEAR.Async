<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceObject;
use Override;

use function json_decode;

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

    /** Invoke the inner request and return the ResourceObject */
    #[Override]
    public function __invoke(array|null $query = null): ResourceObject
    {
        return ($this->inner)($query);
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
     * The inner request's identity (method + URI + links) is what
     * PendingRequests keys pending/results by. AsyncRequest's own
     * $resourceObject is only used for BC field access, and for deferred
     * requests it is a NullResourceObject shared across different URIs, so
     * the inherited class-based hash() would collide. Delegate to the inner
     * request, whose hash() (e.g. DeferredRequest::hash()) is the correct
     * identity.
     */
    #[Override]
    public function hash(): string
    {
        return $this->inner->hash();
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
