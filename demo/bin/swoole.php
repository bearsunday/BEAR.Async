<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use BEAR\AppMeta\Meta;
use BEAR\Package\Module;
use BEAR\Resource\Method;
use BEAR\Resource\ResourceObject;
use BEAR\Swoole\App;
use BEAR\Swoole\SwooleModule;
use BEAR\Swoole\SwooleRequestProvider;
use BEAR\Sunday\Extension\Transfer\TransferInterface;
use Ray\Di\Injector;
use Swoole\Coroutine;
use Swoole\Database\PDOPool;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

if (! class_exists(Server::class)) {
    throw new LogicException('"bear/swoole" is not installed. See http://bearsunday.github.io/manuals/1.0/en/swoole.html');
}

if (isXdebugActive()) {
    fwrite(STDERR, "Xdebug is enabled. Swoole coroutines are not safe with active Xdebug; disable Xdebug or set XDEBUG_MODE=off.\n");
    exit(1);
}

$host = getenv('SWOOLE_HOST') ?: '127.0.0.1';
$port = (int) (getenv('SWOOLE_PORT') ?: 8080);
$context = getenv('APP_CONTEXT') ?: 'prod-swoole-hal-api-app';

Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);

$appModule = (new Module())(new Meta('BEAR\AsyncDemo', $context), $context);
$appModule->override(new SwooleModule());
$injector = new Injector(new SwooleModule($appModule));
$app = $injector->getInstance(App::class);
$prefillPdoPool = getenv('PDO_POOL_PREFILL');
if ($prefillPdoPool === false || $prefillPdoPool !== '0') {
    Coroutine\run(static function () use ($injector): void {
        $injector->getInstance(PDOPool::class)->fill();
    });
}

$http = new Server($host, $port);

$http->on('start', static function () use ($host, $port): void {
    echo "Swoole http server is started at http://{$host}:{$port}" . PHP_EOL;
});

$http->on('request', static function (Request $request, Response $response) use ($app): void {
    $server = SwooleRequestProvider::seed($request);

    if ($app->httpCache->isNotModified()) {
        $app->httpCache->transfer($response);

        return;
    }

    $match = $app->router->match(
        [
            '_GET' => $request->get ?? [],
            '_POST' => $request->post ?? [],
        ],
        $server,
    );

    try {
        $ro = $app->resource->newRequest(Method::from($match->method), $match->path, $match->query)();
        $ro->transfer(new class ($response) implements TransferInterface {
            public function __construct(
                private readonly Response $response,
            ) {
            }

            public function __invoke(ResourceObject $ro, array $server): void
            {
                unset($server);
                $ro->toString();
                foreach ($ro->headers as $key => $value) {
                    $this->response->header($key, (string) $value);
                }

                $this->response->status($ro->code);
                $this->response->end($ro->view);
            }
        }, []);
    } catch (Exception $e) {
        $app->error->transfer($e, $request, $response);
    }
});

$http->start();

function isXdebugActive(): bool
{
    if (! extension_loaded('xdebug')) {
        return false;
    }

    $mode = getenv('XDEBUG_MODE');
    if ($mode !== false) {
        return $mode !== '' && $mode !== 'off';
    }

    $iniMode = ini_get('xdebug.mode');
    if ($iniMode === false) {
        return true;
    }

    return $iniMode !== '' && $iniMode !== 'off';
}
