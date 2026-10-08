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
use Codad5\WPToolkit\Adapters\Migrations\OptionMigrationStore;
use Codad5\WPToolkit\Adapters\Repository\RepositoryFactory;
use Codad5\WPToolkit\Admin\Notices;
use Codad5\WPToolkit\Admin\Pages;
use Codad5\WPToolkit\Assets\AssetManager;
use Codad5\WPToolkit\Assets\JsNamespace;
use Codad5\WPToolkit\Cli\Scaffolder;
use Codad5\WPToolkit\Cli\ToolkitCommand;
use Codad5\WPToolkit\Cli\WpCliConsole;
use Codad5\WPToolkit\Contracts\Cache\CacheStore;
use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Contracts\Container\Container;
use Codad5\WPToolkit\Contracts\Http\HttpClient;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Contracts\View\Renderer;
use Codad5\WPToolkit\Contracts\Data\MigrationStore;
use Codad5\WPToolkit\Data\EntityRegistrar;
use Codad5\WPToolkit\Data\Migrations\MigrationRunner;
use Codad5\WPToolkit\Data\Migrations\Migrator;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Frontend\PublicPages;
use Codad5\WPToolkit\Http\Dispatcher;
use Codad5\WPToolkit\Http\Router;
use Codad5\WPToolkit\Http\Transport\AjaxTransport;
use Codad5\WPToolkit\Http\Transport\RestTransport;
use Codad5\WPToolkit\Support\RateLimit\ClientIp;
use Codad5\WPToolkit\Support\RateLimit\RateLimiter;
use Codad5\WPToolkit\View\PhpTemplateRenderer;
use Codad5\WPToolkit\View\TemplateLocator;

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

        // Views: config 'views' (directories) or {plugin}/views; themes override under {theme}/{slug}/.
        $container->singleton(TemplateLocator::class, static function () use ($config): TemplateLocator {
            $paths = $config->get('views', [dirname($config->file) . '/views']);
            $folder = $config->get('view_theme_folder', $config->slug);
            return new TemplateLocator(
                is_array($paths) ? array_values(array_map('strval', $paths)) : [],
                $folder === false ? null : (string) $folder
            );
        });
        $container->singleton(Renderer::class, static fn (Container $c) => new PhpTemplateRenderer($c->get(TemplateLocator::class)));

        // Assets: declared any time, handed to WordPress only inside the enqueue hooks (C4).
        $container->singleton(AssetManager::class, static function () use ($app, $config): AssetManager {
            $dir = dirname($config->file);
            $library = dirname(__DIR__, 2);
            $assets = new AssetManager(
                $app->identity(),
                new JsNamespace($app->identity(), Application::VERSION),
                $dir,
                self::contentUrl($dir),
                $library,
                self::contentUrl($library),
                $config->textDomain(),
                $dir . '/' . trim((string) $config->get('domain_path', 'languages'), '/')
            );
            $assets->register($app->hooks());
            return $assets;
        });

        $container->singleton(Pages::class, static function (Container $c) use ($app): Pages {
            $pages = new Pages($app->hooks(), $c->get(Renderer::class));
            $pages->register();
            return $pages;
        });
        $container->singleton(PublicPages::class, static function (Container $c) use ($app): PublicPages {
            $public = new PublicPages($app->identity(), $app->hooks(), $c->get(Dispatcher::class), $c->get(Renderer::class), $c->get(ClientIp::class));
            $public->register();
            return $public;
        });
        $container->singleton(Notices::class, static function () use ($app): Notices {
            $notices = new Notices($app->identity(), $app->hooks());
            $notices->register();
            return $notices;
        });

        // `wp {slug} …`, registered by Application::boot() under WP-CLI only.
        $container->singleton(ToolkitCommand::class, static function (Container $c) use ($app, $config): ToolkitCommand {
            $dir = dirname($config->file);
            $namespace = $config->get('namespace') ?? Scaffolder::namespaceFromComposer($dir);
            return new ToolkitCommand(
                new WpCliConsole(),
                Application::VERSION,
                $c->get(Router::class),
                $c->get(PublicPages::class),
                $app->hooks(),
                $c->get(Migrator::class),
                is_string($namespace) && $namespace !== ''
                    ? new Scaffolder($dir, $namespace, $config->slug, $config->textDomain(), dirname(__DIR__, 2) . '/resources/stubs', $c->get(Clock::class))
                    : null
            );
        });

        $container->singleton(MigrationStore::class, static fn () => new OptionMigrationStore($app->identity()));
        $container->singleton(Migrator::class, static fn (Container $c) => new Migrator(
            $app->resolvedMigrations(),
            $c->get(MigrationStore::class),
            $c->get(Clock::class),
            $c->get(Logger::class)
        ));
        $container->singleton(MigrationRunner::class, static fn (Container $c) => new MigrationRunner($c->get(Migrator::class), $app->identity()));

        $container->singleton(Router::class, static function (Container $c) use ($app): Router {
            $router = new Router($app->hooks(), $c->get(RestTransport::class), $c->get(AjaxTransport::class), $app->isDevelopment());
            // Routes are added in providers' boot(); hand them to WordPress once all have booted.
            $app->onBooted(static fn () => $router->register());
            return $router;
        });
    }

    /**
     * The URL of a directory under wp-content — plugins, mu-plugins and themes alike.
     */
    private static function contentUrl(string $dir): string
    {
        $content = wp_normalize_path(WP_CONTENT_DIR);
        $path = wp_normalize_path($dir);

        return str_starts_with($path, $content . '/') ? content_url(substr($path, strlen($content))) : content_url();
    }
}
