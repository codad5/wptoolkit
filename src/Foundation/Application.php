<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Cli\ToolkitCommand;
use Codad5\WPToolkit\Cli\ToolkitNamespace;
use Codad5\WPToolkit\Contracts\Container\Container as ContainerContract;
use Codad5\WPToolkit\Data\Migrations\Migration;
use Codad5\WPToolkit\Data\Migrations\MigrationRunner;
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

    /** @var list<Migration|class-string<Migration>> */
    private array $migrations = [];

    private bool $registered = false;

    /** Providers' boot() is running. */
    private bool $booting = false;

    /** Every provider has booted and the onBooted() callbacks have run. */
    private bool $booted = false;

    private ?ToolkitCompatibility $incompatibility = null;

    /** @var list<\Closure(): void> */
    private array $bootedCallbacks = [];

    private function __construct(private readonly Config $config)
    {
        Coexistence::record(self::VERSION, Coexistence::thisCopyPath(), Coexistence::thisCopyNamespace());

        $this->container = new Container();
        $this->hooks = new HookRegistrar($this->shouldContainHookErrors());

        $this->container->instance(self::class, $this);
        $this->container->instance(Config::class, $config);
        $this->container->instance(Identity::class, new Identity($config->slug));
        $this->container->instance(HookRegistrar::class, $this->hooks);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(ContainerContract::class, $this->container);

        CoreServices::register($this, $this->container);
    }

    /**
     * Run a callback once every provider has booted — immediately if that already happened.
     *
     * @param \Closure(): void $callback
     */
    public function onBooted(\Closure $callback): void
    {
        if ($this->booted) {
            $callback();
            return;
        }

        $this->bootedCallbacks[] = $callback;
    }

    /**
     * WP_DEBUG on a 'local' or 'development' site: errors surface instead of being contained, and
     * misconfigurations (such as a route without an access rule) throw at registration.
     */
    public function isDevelopment(): bool
    {
        $debug = defined('WP_DEBUG') && constant('WP_DEBUG');
        $environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';

        return $debug && in_array($environment, ['local', 'development'], true);
    }

    /**
     * @param string $file The main plugin file (or the theme's functions.php).
     * @param array<string, mixed> $config Must contain a 'slug'. Optional: 'text_domain',
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
     * Versioned data migrations (ADR-0018), as objects or class names (built by the container, so
     * they can take services). They run on activation and on `admin_init` while any is pending.
     *
     * @param list<Migration|class-string<Migration>> $migrations
     */
    public function migrations(array $migrations): self
    {
        if ($this->registered) {
            throw new LifecycleException('Migrations must be added before boot().');
        }

        foreach ($migrations as $migration) {
            if (!$migration instanceof Migration && !is_subclass_of($migration, Migration::class)) {
                throw new InvalidConfigException(sprintf('Every migration must be a %s object or class name.', Migration::class));
            }
            $this->migrations[] = $migration;
        }

        return $this;
    }

    /**
     * @internal CoreServices builds the Migrator from these.
     * @return list<Migration>
     */
    public function resolvedMigrations(): array
    {
        return array_map(
            fn (Migration|string $m): Migration => $m instanceof Migration ? $m : $this->container->get($m),
            $this->migrations
        );
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

        $compatibility = new ToolkitCompatibility($this->config);
        if (!$compatibility->isCompatible()) {
            $this->stayInert($compatibility);
            return $this;
        }

        foreach ($this->providerClasses as $class) {
            $provider = new $class($this);
            $provider->register();
            $this->providers[] = $provider;
        }

        if ($this->migrations !== []) {
            $this->container->get(MigrationRunner::class)->register($this->hooks);
        }

        if (defined('WP_CLI') && constant('WP_CLI') === true && class_exists(\WP_CLI::class)) {
            $command = $this->container->get(ToolkitCommand::class);
            \WP_CLI::add_command($this->config->slug, ToolkitNamespace::class);
            foreach (ToolkitCommand::SUBCOMMANDS as $name => $method) {
                \WP_CLI::add_command($this->config->slug . ' ' . $name, [$command, $method]);
            }
        }

        if ($this->config->get('type', 'plugin') === 'plugin') {
            register_activation_hook($this->config->file, [$this, 'activate']);
            register_deactivation_hook($this->config->file, [$this, 'deactivate']);
        }

        if (did_action('init') > 0) {
            $this->bootProviders();
        } else {
            $this->hooks->addAction('init', [$this, 'bootProviders'], 0);
        }

        return $this;
    }

    /**
     * Whether the application refused to start because the loaded WPToolkit doesn't satisfy
     * `requires_toolkit` (ADR-0020).
     */
    public function isInert(): bool
    {
        return $this->incompatibility !== null;
    }

    /**
     * Start nothing. Refuse activation if this is an activation request; otherwise tell administrators.
     */
    private function stayInert(ToolkitCompatibility $compatibility): void
    {
        $this->incompatibility = $compatibility;

        if ($this->config->get('type', 'plugin') === 'plugin') {
            register_activation_hook($this->config->file, [$this, 'refuseActivation']);
        }

        $this->hooks->addAction('admin_notices', [$this, 'printInertNotice']);
        $this->hooks->addAction('network_admin_notices', [$this, 'printInertNotice']);
    }

    /**
     * @internal Activation hook while inert: stops WordPress recording the plugin as active.
     */
    public function refuseActivation(): void
    {
        if ($this->incompatibility === null) {
            return;
        }

        wp_die(
            wp_kses_post($this->incompatibility->html()),
            esc_html__('Plugin not activated', 'wptoolkit'),
            ['back_link' => true, 'response' => 200]
        );
    }

    /**
     * @internal Admin notice while inert.
     */
    public function printInertNotice(): void
    {
        if ($this->incompatibility === null || !current_user_can('activate_plugins')) {
            return;
        }

        printf(
            '<div class="notice notice-error" role="alert">%s</div>',
            wp_kses_post($this->incompatibility->html())
        );
    }

    /**
     * Run each provider's optional `activate()`, and register the uninstall handler.
     *
     * @internal Registered with register_activation_hook() by boot().
     */
    public function activate(): void
    {
        $uninstall = $this->config->get('uninstall');
        if ($uninstall !== null) {
            if (!is_string($uninstall) || !is_callable([$uninstall, 'uninstall'])) {
                throw new InvalidConfigException(
                    "'uninstall' must name a class with a public static uninstall() method "
                    . '(WordPress stores the callback, so it cannot be an object or closure).'
                );
            }
            register_uninstall_hook($this->config->file, [$uninstall, 'uninstall']);
        }

        $this->callOnProviders('activate');

        if ($this->migrations !== []) {
            $this->container->get(MigrationRunner::class)->runIfPending();
        }
    }

    /**
     * Run each provider's optional `deactivate()`, then remove every hook the application added.
     *
     * @internal Registered with register_deactivation_hook() by boot().
     */
    public function deactivate(): void
    {
        $this->callOnProviders('deactivate');
        $this->shutdown();
    }

    /**
     * Load the consumer's translations, then run each provider's `boot()`.
     *
     * @internal Hooked to `init` by boot(); public only so WordPress can call it.
     */
    public function bootProviders(): void
    {
        if ($this->booting || $this->booted) {
            return;
        }
        $this->booting = true;

        (new LibraryTranslations($this->identity(), LibraryTranslations::bundledDirectory()))->load();
        $this->loadTextDomain();
        $this->callOnProviders('boot');

        // Only now is booting over: onBooted() callbacks queued during the providers' boot()
        // (e.g. the router's registration) run after every provider has added its routes.
        $this->booted = true;
        foreach ($this->bootedCallbacks as $callback) {
            $callback();
        }
        $this->bootedCallbacks = [];
    }

    /**
     * Call an optional lifecycle method on every provider that defines it, injecting its parameters.
     */
    private function callOnProviders(string $method): void
    {
        foreach ($this->providers as $provider) {
            $callable = [$provider, $method];
            if (is_callable($callable)) {
                $this->container->call($callable);
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

    public function identity(): Identity
    {
        return $this->container->get(Identity::class);
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

    /**
     * Contain hook errors in production; let them surface on local/development sites with WP_DEBUG
     * (ADR-0013). `'contain_hook_errors' => bool` in the config overrides both.
     */
    private function shouldContainHookErrors(): bool
    {
        $configured = $this->config->get('contain_hook_errors');
        if (is_bool($configured)) {
            return $configured;
        }

        return !$this->isDevelopment();
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
