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

        // Replace AbstractRequest with AsyncRequest in body
        /**
         * @var string $key
         * @var mixed $value
         */
        foreach ($result->body as $key => $value) {
            if ($value instanceof AbstractRequest) {
                $result->body[$key] = new AsyncRequest($value, $this->allRequests);
            }
        }

        return $result;
    }
}
