<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Renderer;

use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use Ray\Di\Di\Named;
use Swoole\Coroutine;

use function class_exists;
use function extension_loaded;
use function file_put_contents;
use function getmypid;
use function json_encode;
use function microtime;
use function sprintf;
use function usleep;

use const FILE_APPEND;
use const JSON_UNESCAPED_SLASHES;

/**
 * Slowty Template Engine - A playful slow renderer for demo/debugging
 *
 * Like Smarty or Twig, but intentionally slow to simulate template rendering time.
 */
final class SlowtyTemplateEngine implements RenderInterface
{
    public function __construct(
        #[Named('slowty_delay_ms')]
        private readonly int $delayMs = 5,
    ) {
    }

    public function render(ResourceObject $ro): string
    {
        $uri = (string) $ro->uri;
        $start = microtime(true);
        $pid = getmypid();

        // Log render start
        $this->log(sprintf("[%.3f] RENDER START %s pid=%d\n", $start, $uri, $pid));

        // Simulate template rendering delay
        $this->sleep();

        // Simple output showing resource info
        $ro->headers['Content-Type'] = 'application/json';
        $ro->view = json_encode([
            'uri' => $uri,
            'body' => $ro->body,
        ], JSON_UNESCAPED_SLASHES) . "\n";

        $end = microtime(true);
        $elapsed = ($end - $start) * 1000;

        // Log render end
        $this->log(sprintf("[%.3f] RENDER END   %s pid=%d (%.2fms)\n", $end, $uri, $pid, $elapsed));

        return $ro->view;
    }

    private function sleep(): void
    {
        if (extension_loaded('swoole') && class_exists(Coroutine::class) && Coroutine::getCid() !== -1) {
            Coroutine::sleep($this->delayMs / 1000);

            return;
        }

        usleep($this->delayMs * 1000);
    }

    private function log(string $message): void
    {
        file_put_contents('/tmp/render-debug.log', $message, FILE_APPEND);
    }
}
