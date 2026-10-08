<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Assets;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Http\Router;

/**
 * Scripts and styles for one plugin (ports 0.x `EnqueueManager`, fixing C4 by design): declaring an
 * asset only records it, and WordPress is told about it inside `wp_enqueue_scripts`,
 * `admin_enqueue_scripts` or `login_enqueue_scripts` — so "enqueued too early" can't happen.
 *
 * - Handles come from Identity (`my-plugin-admin`), so two plugins never collide.
 * - A `build/x.asset.php` manifest from `@wordpress/scripts` supplies dependencies and version.
 * - Scripts that depend on `wp-i18n` get their translations from the plugin's language directory.
 * - Data goes to `window.wptoolkit[slug]` (JsNamespace), merged, with `toolkitVersion`.
 * - A stylesheet with an `-rtl.css` sibling is swapped for it on right-to-left sites.
 */
final class AssetManager
{
    /** @var array<string, Asset> */
    private array $assets = [];

    private bool $registered = false;

    private ?Router $clientRouter = null;

    /**
     * @param string $baseDir The plugin's (or theme's) directory; relative sources resolve against it.
     * @param string $baseUrl The URL of `$baseDir`.
     * @param string $libraryDir This copy of WPToolkit's root, for the bundled JS client.
     * @param string $libraryUrl The URL of `$libraryDir`.
     */
    public function __construct(
        private readonly Identity $identity,
        private readonly JsNamespace $namespace,
        private readonly string $baseDir,
        private readonly string $baseUrl,
        private readonly string $libraryDir,
        private readonly string $libraryUrl,
        private readonly string $textDomain,
        private readonly string $languagesDir
    ) {
    }

    public function script(string $name, string $src): Asset
    {
        return $this->add(new Asset('script', $name, $src));
    }

    public function style(string $name, string $src): Asset
    {
        return $this->add(new Asset('style', $name, $src));
    }

    /**
     * Load the JS API client for this plugin's routes: `window.wptoolkit[slug].api.call('todos', …)`.
     */
    public function client(Router $router): Asset
    {
        $this->clientRouter = $router;

        return $this->add(new Asset('script', 'wptoolkit-client', $this->libraryDir . '/resources/js/client.js'));
    }

    public function register(HookRegistrar $hooks): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $hooks->addAction('wp_enqueue_scripts', fn () => $this->enqueue(Asset::FRONT, ''));
        $hooks->addAction('admin_enqueue_scripts', fn (string $hook = '') => $this->enqueue(Asset::ADMIN, $hook));
        $hooks->addAction('login_enqueue_scripts', fn () => $this->enqueue(Asset::LOGIN, ''));
    }

    /**
     * @return non-empty-string
     */
    public function handle(string $name): string
    {
        return $this->identity->handle($name);
    }

    /**
     * @internal Runs inside the enqueue hooks.
     */
    public function enqueue(string $context, string $hookSuffix): void
    {
        foreach ($this->assets as $asset) {
            if (!in_array($context, $asset->contexts, true) || ($asset->condition !== null && !($asset->condition)($hookSuffix))) {
                continue;
            }
            $asset->type === 'script' ? $this->enqueueScript($asset) : $this->enqueueStyle($asset);
        }
    }

    private function add(Asset $asset): Asset
    {
        if (isset($this->assets[$asset->name])) {
            throw new InvalidConfigException(sprintf('Asset "%s" is already declared.', $asset->name));
        }

        return $this->assets[$asset->name] = $asset;
    }

    private function enqueueScript(Asset $asset): void
    {
        $handle = $this->handle($asset->name);
        [$deps, $version] = $this->manifest($asset);

        wp_enqueue_script($handle, $this->url($asset->src), $deps, $version, ['in_footer' => $asset->inFooter]);

        $data = $asset->data;
        if ($asset->name === 'wptoolkit-client' && $this->clientRouter !== null) {
            $data['__api'] = $this->clientRouter->clientConfig();
            wp_add_inline_script($handle, $this->namespace->client(), 'after');
        }
        // Always write the entry, so even a script without data finds window.wptoolkit[slug].
        wp_add_inline_script($handle, $this->namespace->merge($data), 'before');

        if (in_array('wp-i18n', $deps, true)) {
            wp_set_script_translations($handle, $this->textDomain, $this->languagesDir);
        }
    }

    private function enqueueStyle(Asset $asset): void
    {
        $handle = $this->handle($asset->name);
        [$deps, $version] = $this->manifest($asset);

        wp_enqueue_style($handle, $this->url($asset->src), $deps, $version, $asset->media);

        $path = $this->path($asset->src);
        if ($path !== null && is_file(substr($path, 0, -4) . '-rtl.css')) {
            wp_style_add_data($handle, 'rtl', 'replace');
        }
    }

    /**
     * Dependencies and version: the `*.asset.php` manifest if there is one, then the asset's own.
     *
     * @return array{list<non-empty-string>, string|false}
     */
    private function manifest(Asset $asset): array
    {
        $deps = $asset->deps;
        $version = $asset->version;

        $path = $this->path($asset->src);
        if ($path !== null) {
            $manifest = (string) preg_replace('/\.(js|css)$/', '.asset.php', $path);
            if ($manifest !== $path && is_file($manifest)) {
                $data = include $manifest;
                if (is_array($data)) {
                    $deps = [...(isset($data['dependencies']) && is_array($data['dependencies']) ? array_map('strval', $data['dependencies']) : []), ...$deps];
                    $version ??= isset($data['version']) && is_scalar($data['version']) ? (string) $data['version'] : null;
                }
            }
            $version ??= is_file($path) ? (string) filemtime($path) : null;
        }

        return [array_values(array_unique(array_filter($deps, static fn (string $d): bool => $d !== ''))), $version ?? false];
    }

    /**
     * The file behind a relative or absolute local source, or null for a URL.
     */
    private function path(string $src): ?string
    {
        if (preg_match('#^(https?:)?//#', $src) === 1) {
            return null;
        }

        return str_starts_with($src, $this->libraryDir) ? $src : rtrim($this->baseDir, '/\\') . '/' . ltrim($src, '/');
    }

    private function url(string $src): string
    {
        if ($this->path($src) === null) {
            return $src;
        }

        return str_starts_with($src, $this->libraryDir)
            ? rtrim($this->libraryUrl, '/') . '/' . ltrim(str_replace('\\', '/', substr($src, strlen($this->libraryDir))), '/')
            : rtrim($this->baseUrl, '/') . '/' . ltrim(str_replace('\\', '/', $src), '/');
    }
}
