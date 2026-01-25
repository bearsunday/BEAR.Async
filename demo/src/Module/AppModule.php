<?php

declare(strict_types=1);

namespace BEAR\AsyncDemo\Module;

use BEAR\AsyncDemo\Annotation\SlowQuery;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Resource\ResourceObject;
use Koriym\EnvJson\EnvJson;
use Ray\AuraSqlModule\AuraSqlModule;
use Ray\MediaQuery\MediaQuerySqlModule;

use function dirname;
use function getenv;

final class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        (new EnvJson())->load(dirname(__DIR__, 2));

        // Database connection from environment
        $dsn = getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__, 2) . '/var/db/blog.sqlite';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';
        $this->install(new AuraSqlModule($dsn, $user, $pass));

        // SQL-based queries
        $appDir = dirname(__DIR__, 2);
        $this->install(new MediaQuerySqlModule(dirname(__DIR__) . '/Query', $appDir . '/sql'));

        // Simulate realistic SQL execution time (10ms per query)
        // Accounts for network latency to database server
        // Resources with #[SlowQuery] attribute will have artificial delay
        $this->bind()->annotatedWith('slow_query_delay_ms')->toInstance(10);
        $this->bindInterceptor(
            $this->matcher->subclassesOf(ResourceObject::class),
            $this->matcher->annotatedWith(SlowQuery::class),
            [SlowQueryInterceptor::class],
        );

        $this->install(new PackageModule());
    }
}
