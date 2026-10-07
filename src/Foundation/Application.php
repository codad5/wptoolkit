<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Contracts\Container\Container as ContainerContract;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;

/**
 * One consuming plugin or theme. Owns its container, its hooks and its providers; nothing is
 * static, so two plugins never share state (ADR-0007).
 *
 * Lifecycle:
 *  1. `create()` — builds the config and container. No WordPress calls.
 *  2. `boot()` — instantiates providers and runs every `register()` straight away.
 *  3. On `init` (or immediately, if `init` already ran): loads the consumer's text domain, then
 *     runs every provider's `boot()`. Translating at this point can't trigger WordPress 6.7's
 *     "translation loading triggered too early" notice.
 *  4. `shutdown()` — removes every hook the application added (plugin deactivation).
 */
final class Application
{
    public const VERSION = '1.0.0-dev';

    private Container $container;

    private HookRegistrar $hooks;

    /** @var list<class-string<ServiceProvider>> */
    private array $providerClasses = [];

    /** @var list<ServiceProvider> */
    private array $providers = [];

    private bool $registered = false;

    private bool $booted = false;

    private function __construct(private readonly Config $config)
    {
        $this->container = new Container();
        $this->hooks = new HookRegistrar();

        $this->container->instance(self::class, $this);
        $this->container->instance(Config::class, $config);
        $this->container->instance(HookRegistrar::class, $this->hooks);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerContract::class, $this->container);
    }

    /**
     * @param string $file The main plugin file (or the theme's functions.php).
     * @param array<string, mixed> $config Must contain a 'slug'. Optional: 'textdomain',
     *        'domain_path' (default 'languages'), 'type' ('plugin' or 'theme', default 'plugin').
     */
    public static function create(string $file, array $config): self
    {
        return new self(Config::fromArray($file, $config));
    }

    /**
     * @param list<class-string<ServiceProvider>> $providers
     */
    public function providers(array $providers): self
    {
        if ($this->registered) {
            throw new LifecycleException('Providers must be added before boot().');
        }

        foreach ($providers as $provider) {
            if (!is_subclass_of($provider, ServiceProvider::class)) {
                throw new InvalidConfigException(sprintf('%s is not a %s.', $provider, ServiceProvider::class));
            }
            $this->providerClasses[] = $provider;
        }

        return $this;
    }

    /**
     * Register every provider now; boot them on `init`.
     */
    public function boot(): self
    {
        if ($this->registered) {
            return $this;
        }
        $this->registered = true;

        foreach ($this->providerClasses as $class) {
            $provider = new $class($this);
            $provider->register();
            $this->providers[] = $provider;
        }

        if (did_action('init') > 0) {
            $this->bootProviders();
        } else {
            $this->hooks->addAction('init', [$this, 'bootProviders'], 0);
        }

        return $this;
    }

    /**
     * Load the consumer's translations, then run each provider's `boot()`.
     *
     * @internal Hooked to `init` by boot(); public only so WordPress can call it.
     */
    public function bootProviders(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $this->loadTextDomain();

        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $this->container->call([$provider, 'boot']);
            }
        }
    }

    /**
     * Remove every hook this application added. Call on plugin deactivation.
     */
    public function shutdown(): void
    {
        $this->hooks->removeAll();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function container(): ContainerContract
    {
        return $this->container;
    }

    public function hooks(): HookRegistrar
    {
        return $this->hooks;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    private function loadTextDomain(): void
    {
        $domain = $this->config->textDomain();
        if (is_textdomain_loaded($domain)) {
            return;
        }

        $relativePath = trim((string) $this->config->get('domain_path', 'languages'), '/');

        if ($this->config->get('type', 'plugin') === 'theme') {
            load_theme_textdomain($domain, dirname($this->config->file) . '/' . $relativePath);
            return;
        }

        load_plugin_textdomain($domain, false, dirname(plugin_basename($this->config->file)) . '/' . $relativePath);
    }
}
