<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

$bootstrap = dirname(__DIR__) . '/vendor/bear/async/bootstrap.php';
if (! file_exists($bootstrap)) {
    // Local checkout fallback: when running from the source repo, bear/async
    // lives at the project root rather than vendor/bear/async.
    $bootstrap = dirname(__DIR__, 2) . '/bootstrap.php';
}

$defaultContext = PHP_SAPI === 'cli' ? 'cli-hal-api-app' : 'hal-api-app';
$context = getenv('APP_CONTEXT') ?: $defaultContext;

exit((require $bootstrap)(
    $context,
    'BEAR\AsyncDemo',
    dirname(__DIR__),
    $GLOBALS,
    $_SERVER,
));
