<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\Annotation\Embed;
use BEAR\Resource\Exception\BadRequestException;
use BEAR\Resource\Exception\EmbedException;
use BEAR\Resource\Exception\LinkException;
use BEAR\Resource\EmbedInterceptorInterface;
use BEAR\Resource\Method;
use BEAR\Resource\Request;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use BEAR\Resource\Types;
use Override;
use Ray\Aop\MethodInvocation;
use Ray\Di\Di\Set;
use Ray\Di\ProviderInterface;

use function array_shift;
use function assert;
use function is_array;
use function is_string;
use function uri_template;

/**
 * Intercepts #[Embed] resources and wraps them with AsyncRequest
 *
 * The async request must be placed in the body before the resource method
 * proceeds, because downstream interceptors such as JsonSchema may serialize
 * the body before rendering.
 *
 * @psalm-import-type Query from Types
 */
final readonly class AsyncEmbedInterceptor implements EmbedInterceptorInterface
{
    private const SELF_LINK = '_self';

    /** @param ProviderInterface<ResourceInterface> $resourceProvider */
    public function __construct(
        #[Set(ResourceInterface::class)]
        private ProviderInterface $resourceProvider,
        private PendingRequests $allRequests,
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

        $result = $invocation->proceed();
        assert($result instanceof ResourceObject);

        if (is_array($result->body)) {
            /**
             * @var string $key
             * @var mixed $value
             */
            foreach ($result->body as $key => $value) {
                $result->body[$key] = $this->wrapAsyncRequests($value);
            }
        }

        return $result;
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
                $this->prepareBody($ro, $embed);

                if ($embed->rel === self::SELF_LINK) {
                    $request = $this->resourceProvider->get()->newRequest(Method::GET, $uri);
                    assert($request instanceof Request);
                    $this->linkSelf($request, $ro);

                    continue;
                }

                assert(is_array($ro->body));

                $ro->body[$embed->rel] = new AsyncRequest(
                    new DeferredRequest($this->resourceProvider, Method::GET, $uri),
                    $this->allRequests,
                );
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

    private function prepareBody(ResourceObject $ro, Embed $embed): void
    {
        if ($ro->body === null) {
            $ro->body = [];
        }

        if (! is_array($ro->body)) {
            throw new LinkException($embed->rel); // @codeCoverageIgnore
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

    private function linkSelf(Request $request, ResourceObject $ro): void
    {
        $result = $request();
        assert(is_array($result->body));
        /** @var mixed $value */
        foreach ($result->body as $key => $value) {
            assert(is_string($key));
            /** @psalm-suppress MixedArrayAssignment */
            $ro->body[$key] = $value; // @phpstan-ignore-line
        }

        $ro->code = $result->code;
    }

    /** @psalm-suppress MixedAssignment */
    private function wrapAsyncRequests(mixed $value): mixed
    {
        if ($value instanceof AsyncRequest) {
            return $value;
        }

        if ($value instanceof AbstractRequest) {
            return new AsyncRequest($value, $this->allRequests);
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->wrapAsyncRequests($v);
            }
        }

        return $value;
    }
}
