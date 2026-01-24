<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\EmbedInterceptorInterface;
use BEAR\Resource\Exception\BadRequestException;
use BEAR\Resource\Exception\EmbedException;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use Override;
use Ray\Aop\MethodInvocation;

use function array_shift;
use function assert;
use function is_array;
use function is_string;
use function uri_template;

/**
 * AsyncEmbedInterceptor collects embed requests for parallel loading
 *
 * This interceptor replaces the standard EmbedInterceptor to enable
 * async/parallel loading of embedded resources. Instead of executing
 * requests immediately, it adds them to EmbedRequests as FutureResource objects.
 *
 * @psalm-import-type Query from \BEAR\Resource\Types
 */
final readonly class AsyncEmbedInterceptor implements EmbedInterceptorInterface
{
    private const SELF_LINK = '_self';

    public function __construct(
        private ResourceInterface $resource,
        private EmbedRequests $embedRequests,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @throws EmbedException
     */
    #[Override]
    public function invoke(MethodInvocation $invocation)
    {
        $ro = $invocation->getThis();
        assert($ro instanceof ResourceObject);
        $query = $this->getArgsByInvocation($invocation);
        $embeds = $invocation->getMethod()->getAnnotations();
        $this->embedResource($embeds, $ro, $query);

        return $invocation->proceed();
    }

    /**
     * @param array<Embed|object> $embeds
     * @param Query               $query
     *
     * @throws EmbedException
     */
    private function embedResource(array $embeds, ResourceObject $ro, array $query): void
    {
        foreach ($embeds as $embed) {
            if (! $embed instanceof Embed) {
                continue;
            }

            try {
                $templateUri = $this->getFullUri($embed->src, $ro);
                $uri = uri_template($templateUri, $query);
                /** @var Request $request */
                $request = $this->resource->get->uri($uri); // @phpstan-ignore property.notFound, method.notFound
                $this->prepareBody($ro, $embed);

                if ($embed->rel === self::SELF_LINK) {
                    $this->linkSelf($request, $ro);

                    continue;
                }

                assert(is_array($ro->body));

                // Add request as FutureResource instead of immediate execution
                $ro->body[$embed->rel] = $this->embedRequests->add(clone $request);
            } catch (BadRequestException $e) {
                throw new EmbedException($embed->src, 500, $e);
            }
        }
    }

    private function getFullUri(string $uri, ResourceObject $ro): string
    {
        if ($uri[0] === '/') {
            $uri = "{$ro->uri->scheme}://{$ro->uri->host}" . $uri;
        }

        return $uri;
    }

    public function prepareBody(ResourceObject $ro, Embed $embed): void
    {
        if ($ro->body === null) {
            $ro->body = [];
        }

        if (! is_array($ro->body)) {
            throw new LinkException($embed->rel);
        }
    }

    /**
     * @param MethodInvocation<object> $invocation
     *
     * @return Query
     */
    private function getArgsByInvocation(MethodInvocation $invocation): array
    {
        /** @var list<scalar> $args */
        $args = $invocation->getArguments()->getArrayCopy();
        $params = $invocation->getMethod()->getParameters();
        $namedParameters = [];
        foreach ($params as $param) {
            $namedParameters[$param->name] = array_shift($args);
        }

        return $namedParameters;
    }

    public function linkSelf(Request $request, ResourceObject $ro): void
    {
        $result = $request();
        assert(is_array($result->body));
        assert(is_array($ro->body));
        /** @var mixed $value */
        foreach ($result->body as $key => $value) {
            assert(is_string($key));
            $ro->body[$key] = $value;
        }

        $ro->code = $result->code;
    }
}
