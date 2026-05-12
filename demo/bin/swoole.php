<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';
$bootstrap = dirname(__DIR__) . '/vendor/bear/swoole/bootstrap.php';
if (! file_exists($bootstrap)) {
    throw new LogicException('"bear/swoole" is not installed. See http://bearsunday.github.io/manuals/1.0/en/swoole.html');
}

$host = getenv('SWOOLE_HOST') ?: '127.0.0.1';
$port = (int) (getenv('SWOOLE_PORT') ?: 8080);
$context = getenv('APP_CONTEXT') ?: 'prod-hal-api-app';

exit((require $bootstrap)(
    $context,
    'BEAR\AsyncDemo',
    $host,
    $port
));
