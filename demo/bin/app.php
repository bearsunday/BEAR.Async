<?php

declare(strict_types=1);

use BEAR\AsyncDemo\Bootstrap;

require dirname(__DIR__) . '/autoload.php';

$defaultContext = PHP_SAPI === 'cli' ? 'cli-hal-api-app' : 'hal-api-app';
$context = getenv('APP_CONTEXT') ?: $defaultContext;

exit((new Bootstrap())($context, $GLOBALS, $_SERVER));
