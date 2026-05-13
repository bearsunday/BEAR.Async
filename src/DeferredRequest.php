<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\Method;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Types;
use BEAR\Resource\Uri;
use Override;
use Ray\Di\ProviderInterface;

use function array_merge;
use function md5;
use function serialize;

/**
 * Defers ResourceObject construction until the request is actually invoked.
 *
 * Swoole pooled PDO bindings may borrow a connection while resource
 * dependencies are built. Eagerly constructing all embedded requests can
 * therefore reserve multiple connections before any query runs. This request
 * keeps only the URI until PendingRequests dispatches the batch.
 *
 * @psalm-import-type Query from Types
 */
final class DeferredRequest extends AbstractRequest
{
    /**
     * @param ProviderInterface<ResourceInterface> $resourceProvider
     * @param Query                               $query
     * @param list<LinkType>                      $links
     */
    public function __construct(
        private readonly ProviderInterface $resourceProvider,
        Method $method,
        private readonly string $requestUri,
        array $query = [],
        array $links = [],
    ) {
        $resourceObject = new NullResourceObject();
        $resourceObject->uri = new Uri($requestUri, $query);

        parent::__construct(
            new class implements InvokerInterface {
                public function invoke(AbstractRequest $request): ResourceObject
                {
                    unset($request);

                    return new NullResourceObject();
                }
            },
            $resourceObject,
            $method,
            $query,
            $links,
        );
        $this->uri = $this->toUri();
    }

    /** @param Query|null $query */
    #[Override]
    public function __invoke(array|null $query = null): ResourceObject
    {
        if ($query !== null) {
            $this->query = array_merge($this->query, $query);
        }

        $request = $this->resourceProvider->get()->newRequest($this->method, $this->requestUri, $this->query);
        foreach ($this->links as $link) {
            match ($link->type) {
                LinkType::SELF_LINK => $request->linkSelf($link->key),
                LinkType::NEW_LINK => $request->linkNew($link->key),
                LinkType::CRAWL_LINK => $request->linkCrawl($link->key),
                default => null,
            };
        }

        return $request();
    }

    /** @param Query $query */
    #[Override]
    public function withQuery(array $query): RequestInterface
    {
        $this->query = $query;
        $this->uri = $this->toUri();
        $this->resourceObject->uri = new Uri($this->requestUri, $this->query);

        return $this;
    }

    /** @param Query $query */
    #[Override]
    public function addQuery(array $query): RequestInterface
    {
        $this->query = array_merge($this->query, $query);
        $this->uri = $this->toUri();
        $this->resourceObject->uri = new Uri($this->requestUri, $this->query);

        return $this;
    }

    #[Override]
    public function toUriWithMethod(): string
    {
        return "{$this->method->value} {$this->toUri()}";
    }

    #[Override]
    public function toUri(): string
    {
        return (string) new Uri($this->requestUri, $this->query);
    }

    #[Override]
    public function hash(): string
    {
        return md5($this->method->value . $this->toUri() . serialize($this->links));
    }

    #[Override]
    public function linkSelf(string $linkKey): RequestInterface
    {
        $this->links[] = new LinkType($linkKey, LinkType::SELF_LINK);

        return $this;
    }

    #[Override]
    public function linkNew(string $linkKey): RequestInterface
    {
        $this->links[] = new LinkType($linkKey, LinkType::NEW_LINK);

        return $this;
    }

    #[Override]
    public function linkCrawl(string $linkKey): RequestInterface
    {
        $this->links[] = new LinkType($linkKey, LinkType::CRAWL_LINK);

        return $this;
    }
}
