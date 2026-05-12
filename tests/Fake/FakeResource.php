<?php

declare(strict_types=1);

namespace BEAR\Async\Fake;

use BEAR\Resource\AbstractUri;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\RequestInterface;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Types;

use function http_build_query;

/**
 * @psalm-import-type Query from Types
 */
final class FakeResource implements ResourceInterface
{
    /** @var list<array{method: Method, uri: string, query: Query}> */
    public array $newRequests = [];

    public function newInstance($uri): ResourceObject
    {
        return new FakeResourceObject((string) $uri);
    }

    public function object(ResourceObject $ro): RequestInterface
    {
        return new Request(new FakeInvoker($ro), $ro, Method::GET, []);
    }

    public function uri($uri): RequestInterface
    {
        $ro = $this->newInstance($uri);

        return new Request(new FakeInvoker($ro), $ro, Method::GET, []);
    }

    public function newRequest(Method $method, string $uri, array $query = []): RequestInterface
    {
        $this->newRequests[] = ['method' => $method, 'uri' => $uri, 'query' => $query];
        $ro = new FakeResourceObject($uri);

        return new Request(new FakeInvoker($ro), $ro, $method, $query);
    }

    public function crawl(string $uri, string $linkKey, array $query = []): ResourceObject
    {
        unset($linkKey);

        return $this->get($uri, $query);
    }

    public function href(string $rel, array $query = [], ResourceObject|null $ro = null): ResourceObject
    {
        unset($ro);

        return $this->get($rel, $query);
    }

    public function get(string $uri, array $query = []): ResourceObject
    {
        return new FakeResourceObject($this->uriWithQuery($uri, $query));
    }

    public function post(string $uri, array $query = []): ResourceObject
    {
        return $this->get($uri, $query);
    }

    public function put(string $uri, array $query = []): ResourceObject
    {
        return $this->get($uri, $query);
    }

    public function patch(string $uri, array $query = []): ResourceObject
    {
        return $this->get($uri, $query);
    }

    public function delete(string $uri, array $query = []): ResourceObject
    {
        return $this->get($uri, $query);
    }

    public function head(string $uri, array $query = []): ResourceObject
    {
        return $this->get($uri, $query);
    }

    public function options(string $uri, array $query = []): ResourceObject
    {
        return $this->get($uri, $query);
    }

    /** @param Query $query */
    private function uriWithQuery(string|AbstractUri $uri, array $query): string
    {
        if ($query === []) {
            return (string) $uri;
        }

        return (string) $uri . '?' . http_build_query($query);
    }
}
