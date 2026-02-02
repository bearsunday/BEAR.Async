<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Resource\AbstractRequest;
use BEAR\Resource\EmbedInterceptorInterface;
use BEAR\Resource\ResourceObject;
use Override;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\Di\Di\Named;

use function assert;
use function is_array;

/**
 * Intercepts #[Embed] resources and wraps them with AsyncRequest
 *
 * This interceptor decorates the standard EmbedInterceptor, replacing
 * AbstractRequest objects in the body with AsyncRequest objects that
 * enable parallel execution when rendered.
 */
final class AsyncEmbedInterceptor implements EmbedInterceptorInterface
{
    public function __construct(
        #[Named('async.embed.inner')] private readonly MethodInterceptor $inner,
        private readonly PendingRequests $allRequests,
    ) {
    }

    /** @return ResourceObject */
    #[Override]
    public function invoke(MethodInvocation $invocation)
    {
        $result = $this->inner->invoke($invocation);
        assert($result instanceof ResourceObject);

        if (! is_array($result->body)) {
            return $result;
        }

        // Replace AbstractRequest with AsyncRequest in body (recursively)
        /**
         * @var string $key
         * @var mixed $value
         */
        foreach ($result->body as $key => $value) {
            $result->body[$key] = $this->wrapAsyncRequests($value);
        }

        return $result;
    }

    /** @psalm-suppress MixedAssignment */
    private function wrapAsyncRequests(mixed $value): mixed
    {
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
