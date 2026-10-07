<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Adapters\Cache\CacheFactory;
use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Adapters\Http\WpHttpClient;
use Codad5\WPToolkit\Adapters\Log\LoggerFactory;
use Codad5\WPToolkit\Adapters\Repository\RepositoryFactory;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Contracts\Container\Container;
use Codad5\WPToolkit\Contracts\Http\HttpClient;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Data\EntityRegistrar;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Router;
use Codad5\WPToolkit\Http\Transport\AjaxTransport;
use Codad5\WPToolkit\Http\Transport\RestTransport;
use Codad5\WPToolkit\Support\RateLimit\ClientIp;
use Codad5\WPToolkit\Support\RateLimit\RateLimiter;

/**
 * The services every application gets, as lazy singletons — nothing is built until something asks
 * for it. Each can be rebound by a provider's register(), e.g. to swap the HttpClient.
 */
final class CoreServices
{
    public static function register(Application $app, Container $container): void
    {
        $config = $app->config();

        $container->singleton(Clock::class, static fn () => new SystemClock());
        $container->singleton(CacheFactory::class, static fn (Container $c) => new CacheFactory($config, $app->identity(), $c->get(Clock::class)));
        $container->singleton(CacheStore::class, static fn (Container $c) => $c->get(CacheFactory::class)->make());
        $container->singleton(Logger::class, static fn () => (new LoggerFactory($config, $app->hooks()))->make());
        $container->singleton(HttpClient::class, static fn () => new WpHttpClient());

        $container->singleton(ClientIp::class, static function () use ($config): ClientIp {
            $proxies = $config->get('trusted_proxies', []);
            return new ClientIp(is_array($proxies) ? array_values(array_map('strval', $proxies)) : []);
        });
        $container->singleton(RateLimiter::class, static fn (Container $c) => new RateLimiter(
            $c->get(CacheFactory::class)->make('rate'),
            $c->get(Clock::class),
            $c->get(Logger::class)
        ));

        $container->singleton(Dispatcher::class, static fn (Container $c) => new Dispatcher(
            $c,
            $app->identity(),
            $c->get(Logger::class),
            $c->get(RateLimiter::class),
            $app->isDevelopment()
        ));
        $container->singleton(RestTransport::class, static fn (Container $c) => new RestTransport(
            $c->get(Dispatcher::class),
            $app->identity(),
            $c->get(ClientIp::class)
        ));
        $container->singleton(AjaxTransport::class, static fn (Container $c) => new AjaxTransport(
            $c->get(Dispatcher::class),
            $app->identity(),
            $app->hooks(),
            $c->get(ClientIp::class)
        ));
        $container->singleton(FieldTypes::class, static fn () => new FieldTypes());
        $container->singleton(RepositoryFactory::class, static fn (Container $c) => new RepositoryFactory(
            $c->get(FieldTypes::class),
            $app->identity(),
            $c->get(CacheFactory::class)
        ));
        $container->singleton(EntityRegistrar::class, static fn (Container $c) => new EntityRegistrar(
            $app->hooks(),
            $c->get(FieldTypes::class),
            $app->identity(),
            $c->get(CacheFactory::class)->make('flash')
        ));

        $container->singleton(Router::class, static function (Container $c) use ($app): Router {
            $router = new Router($app->hooks(), $c->get(RestTransport::class), $c->get(AjaxTransport::class), $app->isDevelopment());
            // Routes are added in providers' boot(); hand them to WordPress once all have booted.
            $app->onBooted(static fn () => $router->register());
            return $router;
        });
    }
}
