<?php

declare(strict_types=1);

namespace BEAR\Async;

use BEAR\Async\Exception\PdoProxyExtractionException;
use PDO;
use ReflectionException;
use ReflectionProperty;
use Swoole\Database\PDOProxy;
use Throwable;

use function sprintf;

/**
 * Reads the real PDO instance out of Swoole's PDOProxy
 *
 * Swoole stores the wrapped PDO on a private `__object` property. We need
 * direct access to bypass the proxy's magic call machinery when feeding the
 * connection into Aura\Sql\DecoratedPdo. Reflection is the only public-API
 * affordance, so we cache the ReflectionProperty once per process and wrap
 * any failure in a domain exception so callers don't have to know about
 * Swoole internals.
 *
 * @internal
 */
final class PdoProxyExtractor
{
    private const PROXY_OBJECT_PROPERTY = '__object';

    private static ReflectionProperty|null $proxyObjectProperty = null;

    /** @throws PdoProxyExtractionException */
    public static function extract(PDOProxy $proxy): PDO
    {
        try {
            $property = self::$proxyObjectProperty ??= new ReflectionProperty(PDOProxy::class, self::PROXY_OBJECT_PROPERTY);
            /** @var mixed $value */
            $value = $property->getValue($proxy);
        } catch (ReflectionException $e) {
            throw new PdoProxyExtractionException(sprintf(
                'Failed to read Swoole\\Database\\PDOProxy::$%s. Swoole internals may have changed.',
                self::PROXY_OBJECT_PROPERTY,
            ), 0, $e);
        } catch (Throwable $e) { // @codeCoverageIgnoreStart
            throw new PdoProxyExtractionException(sprintf(
                'Failed to read Swoole\\Database\\PDOProxy::$%s.',
                self::PROXY_OBJECT_PROPERTY,
            ), 0, $e);
        } // @codeCoverageIgnoreEnd

        if (! $value instanceof PDO) {
            throw new PdoProxyExtractionException(sprintf(
                'Swoole\\Database\\PDOProxy::$%s did not hold a PDO instance.',
                self::PROXY_OBJECT_PROPERTY,
            ));
        }

        return $value;
    }
}
