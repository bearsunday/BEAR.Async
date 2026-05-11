<?php

declare(strict_types=1);

use BEAR\Async\Module\AsyncParallelBootstrapModule;
use BEAR\Package\Injector;
use BEAR\Resource\ResourceObject;
use BEAR\Sunday\Extension\Application\AppInterface;

/**
 * Library bootstrap for ext-parallel async execution.
 *
 * User's `bin/async.php` requires this file and invokes the returned closure
 * with the desired execution profile (context, app name, app dir, globals,
 * server, optional pool size). The closure builds an override injector with
 * `AsyncParallelBootstrapModule` on top of the user's AppModule, then runs
 * the standard BEAR.Sunday request lifecycle.
 *
 * AppModule stays ignorant of execution form — context is supplied here from
 * the entrypoint, mirroring how `vendor/bear/swoole/bootstrap.php` works for
 * the Swoole HTTP server profile.
 *
 * @return Closure(non-empty-string, non-empty-string, non-empty-string, array<string, mixed>, array<string, mixed>, positive-int|null=): int
 */
return static function (
    string $context,
    string $name,
    string $appDir,
    array $globals,
    array $server,
    int|null $poolSize = null,
): int {
    $injector = Injector::getOverrideInstance(
        $name,
        $context,
        $appDir,
        new AsyncParallelBootstrapModule($context, $poolSize),
    );

    /** @var AppInterface $app */
    $app = $injector->getInstance(AppInterface::class);

    if ($app->httpCache->isNotModified($server)) {
        $app->httpCache->transfer();

        return 0;
    }

    $request = $app->router->match($globals, $server);
    try {
        $response = $app->resource->{$request->method}->uri($request->path)($request->query);
        assert($response instanceof ResourceObject);
        $response->transfer($app->responder, $server);

        return 0;
    } catch (Throwable $e) {
        $app->throwableHandler->handle($e, $request)->transfer();

        return 1;
    }
};
