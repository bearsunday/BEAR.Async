<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\Exception\UriException;
use BEAR\Resource\InvokerInterface;
use BEAR\Resource\LinkType;
use BEAR\Resource\Method;
use BEAR\Resource\NullResourceObject;
use BEAR\Resource\Request;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Types;
use BEAR\Resource\Uri;
use Override;
use Ray\Di\ProviderInterface;

use function array_merge;
use function explode;
use function md5;
use function parse_url;
use function preg_match;
use function preg_match_all;
use function serialize;

use const PHP_URL_FRAGMENT;
use const PREG_SET_ORDER;

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
    /** Fragment-less; a `#link=...` fragment is parsed into $this->links by splitLinks() instead. */
    private readonly string $requestUri;

    /**
     * @param ProviderInterface<ResourceInterface> $resourceProvider
     * @param Query                               $query
     * @param list<LinkType>                      $links
     *
     * @throws UriException if $requestUri carries a fragment other than `#link=type:rel,...`
     */
    public function __construct(
        private readonly ProviderInterface $resourceProvider,
        Method $method,
        string $requestUri,
        array $query = [],
        array $links = [],
    ) {
        [$requestUri, $fragmentLinks] = self::splitLinks($requestUri);
        $this->requestUri = $requestUri;
        $links = array_merge($fragmentLinks, $links);

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

    /**
     * Split off a `#link=type:rel,...` fragment into LinkType[].
     *
     * A private copy of Resource::links()'s grammar, since bear/resource
     * exposes no public parser for it. Any other non-empty fragment
     * throws UriException, matching bear/resource.
     *
     * @return array{0: string, 1: list<LinkType>}
     *
     * @throws UriException
     */
    private static function splitLinks(string $uri): array
    {
        $fragment = (string) parse_url($uri, PHP_URL_FRAGMENT);
        if ($fragment === '') {
            return [$uri, []];
        }

        $link = '(self|new|crawl):([A-Za-z0-9_-]+)';
        if (preg_match("/\Alink={$link}(,{$link})*\z/", $fragment) !== 1) {
            throw new UriException($uri, 500);
        }

        preg_match_all("/{$link}/", $fragment, $matches, PREG_SET_ORDER);
        $links = [];
        foreach ($matches as [, $type, $rel]) {
            $links[] = new LinkType($rel, $type);
        }

        [$base] = explode('#', $uri, 2);

        return [$base, $links];
    }

    /** @param Query|null $query */
    #[Override]
    public function __invoke(array|null $query = null): ResourceObject
    {
        if ($query !== null) {
            $this->query = array_merge($this->query, $query);
            $this->result = null;
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
        $this->result = null;

        return $this;
    }

    /** @param Query $query */
    #[Override]
    public function addQuery(array $query): RequestInterface
    {
        $this->query = array_merge($this->query, $query);
        $this->uri = $this->toUri();
        $this->resourceObject->uri = new Uri($this->requestUri, $this->query);
        $this->result = null;

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
        $ro = new NullResourceObject();
        $ro->uri = new Uri($this->requestUri, $this->query);

        // Request owns the format of the links in a URI
        return (new Request($this->invoker, $ro, $this->method, $ro->uri->query, $this->links))->toUri();
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
        $this->uri = $this->toUri();
        $this->result = null;

        return $this;
    }

    #[Override]
    public function linkNew(string $linkKey): RequestInterface
    {
        $this->links[] = new LinkType($linkKey, LinkType::NEW_LINK);
        $this->uri = $this->toUri();
        $this->result = null;

        return $this;
    }

    #[Override]
    public function linkCrawl(string $linkKey): RequestInterface
    {
        $this->links[] = new LinkType($linkKey, LinkType::CRAWL_LINK);
        $this->uri = $this->toUri();
        $this->result = null;

        return $this;
    }
}
