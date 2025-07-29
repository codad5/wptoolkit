<?php

/**
 * Complete Enhanced Enqueue Manager with Manual Control and Dynamic Localization
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use InvalidArgumentException;

/**
 * Enhanced Enqueue Manager class with manual control and dynamic localization.
 */
final class EnqueueManager
{
    /**
     * Registered script groups.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $script_groups = [];

    /**
     * Registered style groups.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $style_groups = [];

    /**
     * Individual scripts not in groups.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $individual_scripts = [];

    /**
     * Individual styles not in groups.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $individual_styles = [];

    /**
     * Localization data for scripts.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $localizations = [];

    /**
     * Inline scripts and styles.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $inline_scripts = [];
    protected array $inline_styles = [];

    /**
     * Application slug for identification.
     */
    protected string $app_slug;

    /**
     * Text domain for translations.
     */
    protected string $text_domain;

    /**
     * Config instance (optional dependency).
     */
    protected ?Config $config = null;

    /**
     * Base URL for assets.
     */
    protected string $base_url;

    /**
     * Base path for assets.
     */
    protected string $base_path;

    /**
     * Asset version for cache busting.
     */
    protected string $version;

    /**
     * Whether hooks have been registered.
     */
    protected bool $hooks_registered = false;

    /**
     * Whether to auto-enqueue groups when created.
     */
    protected bool $auto_enqueue_groups = false;

    /**
     * Tracks which groups have been enqueued to prevent duplicates.
     *
     * @var array<string, bool>
     */
    protected array $enqueued_groups = [];

    /**
     * Global localization data for wp_toolkit namespace.
     *
     * @var array<string, mixed>
     */
    protected static array $global_localization = [];

    /**
     * Whether global localization has been output.
     */
    protected static bool $global_localization_output = false;

    /**
     * Enhanced constructor with auto-enqueue control.
     */
    public function __construct(
        Config|string $config_or_slug,
        ?string $base_url = null,
        ?string $base_path = null,
        ?string $version = null,
        bool $auto_enqueue_groups = false
    ) {
        $this->auto_enqueue_groups = $auto_enqueue_groups;
        $this->parseConfigOrSlug($config_or_slug);
        $this->base_url = $base_url ?? $this->getDefaultBaseUrl();
        $this->base_path = $base_path ?? $this->getDefaultBasePath();
        $this->version = $version ?? $this->getDefaultVersion();
        $this->registerHooks();
    }

    /**
     * Enhanced static factory method.
     */
    public static function create(
        Config|string $config_or_slug,
        ?string $base_url = null,
        ?string $base_path = null,
        ?string $version = null,
        bool $auto_enqueue_groups = false
    ): static {
        return new static($config_or_slug, $base_url, $base_path, $version, $auto_enqueue_groups);
    }

    /**
     * Enhanced createScriptGroup - only auto-enqueue if enabled.
     */
    public function createScriptGroup(string $group_name, array $config = []): static
    {
        $group_name = sanitize_key($group_name);

        $defaults = [
            'condition' => null,
            'hook' => 'wp_enqueue_scripts',
            'priority' => 10,
            'admin_only' => false,
            'frontend_only' => false,
            'description' => '',
            'auto_enqueue' => $this->auto_enqueue_groups, // Group-level override
        ];

        $this->script_groups[$group_name] = [
            'config' => array_merge($defaults, $config),
            'scripts' => [],
        ];

        return $this;
    }

    /**
     * Enhanced createStyleGroup - only auto-enqueue if enabled.
     */
    public function createStyleGroup(string $group_name, array $config = []): static
    {
        $group_name = sanitize_key($group_name);

        $defaults = [
            'condition' => null,
            'hook' => 'wp_enqueue_scripts',
            'priority' => 10,
            'admin_only' => false,
            'frontend_only' => false,
            'description' => '',
            'auto_enqueue' => $this->auto_enqueue_groups, // Group-level override
        ];

        $this->style_groups[$group_name] = [
            'config' => array_merge($defaults, $config),
            'styles' => [],
        ];

        return $this;
    }

    /**
     * Add a script to a group.
     */
    public function addScriptToGroup(
        string $group_name,
        string $handle,
        string $src,
        array $deps = [],
        array $config = []
    ): EnqueueManager {
        if (!isset($this->script_groups[$group_name])) {
            $this->createScriptGroup($group_name);
        }

        $defaults = [
            'version' => $this->version,
            'in_footer' => true,
            'condition' => null,
            'localize' => null,
            'inline_before' => '',
            'inline_after' => '',
            'strategy' => '', // defer, async (WP 6.3+)
        ];

        $script_config = array_merge($defaults, $config, [
            'src' => $this->resolveAssetUrl($src),
            'deps' => $deps,
        ]);

        $this->script_groups[$group_name]['scripts'][$handle] = $script_config;

        return $this;
    }

    /**
     * Add a style to a group.
     */
    public function addStyleToGroup(
        string $group_name,
        string $handle,
        string $src,
        array $deps = [],
        array $config = []
    ): static {
        if (!isset($this->style_groups[$group_name])) {
            $this->createStyleGroup($group_name);
        }

        $defaults = [
            'version' => $this->version,
            'media' => 'all',
            'condition' => null,
            'inline_before' => '',
            'inline_after' => '',
        ];

        $style_config = array_merge($defaults, $config, [
            'src' => $this->resolveAssetUrl($src),
            'deps' => $deps,
        ]);

        $this->style_groups[$group_name]['styles'][$handle] = $style_config;

        return $this;
    }

    /**
     * Add multiple scripts to a group at once.
     */
    public function addScriptsToGroup(string $group_name, array $scripts): static
    {
        foreach ($scripts as $handle => $script_config) {
            $src = $script_config['src'] ?? '';
            $deps = $script_config['deps'] ?? [];
            unset($script_config['src'], $script_config['deps']);

            $this->addScriptToGroup($group_name, $handle, $src, $deps, $script_config);
        }

        return $this;
    }

    /**
     * Add multiple styles to a group at once.
     */
    public function addStylesToGroup(string $group_name, array $styles): static
    {
        foreach ($styles as $handle => $style_config) {
            $src = $style_config['src'] ?? '';
            $deps = $style_config['deps'] ?? [];
            unset($style_config['src'], $style_config['deps']);

            $this->addStyleToGroup($group_name, $handle, $src, $deps, $style_config);
        }

        return $this;
    }

    /**
     * Add an individual script (not in a group).
     */
    public function addScript(
        string $handle,
        string $src,
        array $deps = [],
        array $config = []
    ): static {
        $defaults = [
            'version' => $this->version,
            'in_footer' => true,
            'condition' => null,
            'hook' => 'wp_enqueue_scripts',
            'priority' => 10,
            'localize' => null,
            'inline_before' => '',
            'inline_after' => '',
            'strategy' => '',
        ];

        $script_config = array_merge($defaults, $config, [
            'src' => $this->resolveAssetUrl($src),
            'deps' => $deps,
        ]);

        $this->individual_scripts[$handle] = $script_config;

        return $this;
    }

    /**
     * Add an individual style (not in a group).
     */
    public function addStyle(
        string $handle,
        string $src,
        array $deps = [],
        array $config = []
    ): static {
        $defaults = [
            'version' => $this->version,
            'media' => 'all',
            'condition' => null,
            'hook' => 'wp_enqueue_scripts',
            'priority' => 10,
            'inline_before' => '',
            'inline_after' => '',
        ];

        $style_config = array_merge($defaults, $config, [
            'src' => $this->resolveAssetUrl($src),
            'deps' => $deps,
        ]);

        $this->individual_styles[$handle] = $style_config;

        return $this;
    }

    /**
     * Add localization data to a script.
     */
    public function addLocalization(string $handle, string $object_name, array $data): static
    {
        $this->localizations[$handle] = [
            'object_name' => $object_name,
            'data' => $data,
        ];

        return $this;
    }

    /**
     * Update localization data for a script (dynamic data support).
     */
    public function updateLocalization(string $handle, array $data, bool $replace = false): static
    {
        if ($replace || !isset($this->localizations[$handle])) {
            $this->localizations[$handle] = [
                'object_name' => $this->app_slug . 'Data',
                'data' => $data,
            ];
        } else {
            $this->localizations[$handle]['data'] = array_merge(
                $this->localizations[$handle]['data'],
                $data
            );
        }

        return $this;
    }

    /**
     * Add inline script.
     */
    public function addInlineScript(string $handle, string $script, string $position = 'after'): static
    {
        if (!isset($this->inline_scripts[$handle])) {
            $this->inline_scripts[$handle] = ['before' => [], 'after' => []];
        }

        $this->inline_scripts[$handle][$position][] = $script;

        return $this;
    }

    /**
     * Add inline style.
     */
    public function addInlineStyle(string $handle, string $style, string $position = 'after'): static
    {
        if (!isset($this->inline_styles[$handle])) {
            $this->inline_styles[$handle] = ['before' => [], 'after' => []];
        }

        $this->inline_styles[$handle][$position][] = $style;

        return $this;
    }

    /**
     * Add data to global wp_toolkit namespace.
     */
    public function addGlobalData(array $data, bool $replace = false): static
    {
        if ($replace) {
            self::$global_localization[$this->app_slug] = $data;
        } else {
            if (!isset(self::$global_localization[$this->app_slug])) {
                self::$global_localization[$this->app_slug] = [];
            }
            self::$global_localization[$this->app_slug] = array_merge(
                self::$global_localization[$this->app_slug],
                $data
            );
        }

        return $this;
    }

    /**
     * Manual enqueue method - only enqueues if conditions are met.
     */
    public function enqueueGroup(string $group_name, string $type = 'both'): static
    {
        // Prevent duplicate enqueuing
        $key = $group_name . '_' . $type;
        if (isset($this->enqueued_groups[$key])) {
            return $this;
        }

        if ($type === 'both' || $type === 'scripts') {
            $this->enqueueScriptGroup($group_name);
        }

        if ($type === 'both' || $type === 'styles') {
            $this->enqueueStyleGroup($group_name);
        }

        $this->enqueued_groups[$key] = true;
        return $this;
    }

    /**
     * Enqueue multiple groups.
     */
    public function enqueueGroups(array $group_names, string $type = 'both'): static
    {
        foreach ($group_names as $group_name) {
            $this->enqueueGroup($group_name, $type);
        }

        return $this;
    }

    /**
     * Enqueue scripts and styles by handles.
     */
    public function enqueueByHandles(array $handles): static
    {
        foreach ($handles as $handle) {
            // Check individual scripts first
            if (isset($this->individual_scripts[$handle])) {
                $this->enqueueIndividualScript($handle, $this->individual_scripts[$handle]);
                continue;
            }

            // Check individual styles
            if (isset($this->individual_styles[$handle])) {
                $this->enqueueIndividualStyle($handle, $this->individual_styles[$handle]);
                continue;
            }

            // Check in groups
            foreach ($this->script_groups as $group_name => $group_data) {
                if (isset($group_data['scripts'][$handle])) {
                    $this->enqueueIndividualScript($handle, $group_data['scripts'][$handle]);
                    break;
                }
            }

            foreach ($this->style_groups as $group_name => $group_data) {
                if (isset($group_data['styles'][$handle])) {
                    $this->enqueueIndividualStyle($handle, $group_data['styles'][$handle]);
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Check if a group should be loaded based on conditions.
     */
    public function shouldLoadGroup(array $group_config): bool
    {
        // Check admin/frontend restrictions
        if ($group_config['admin_only'] && !is_admin()) {
            return false;
        }

        if ($group_config['frontend_only'] && is_admin()) {
            return false;
        }

        // Check custom condition
        if ($group_config['condition'] && is_callable($group_config['condition'])) {
            return (bool) call_user_func($group_config['condition']);
        }

        return true;
    }

    /**
     * Get current localization data for a script.
     */
    public function getLocalizationData(string $handle): ?array
    {
        return $this->localizations[$handle]['data'] ?? null;
    }

    /**
     * Check if a group is enqueued.
     */
    public function isGroupEnqueued(string $group_name, string $type = 'both'): bool
    {
        $key = $group_name . '_' . $type;
        return isset($this->enqueued_groups[$key]);
    }

    /**
     * Get all registered script groups.
     */
    public function getScriptGroups(): array
    {
        return array_keys($this->script_groups);
    }

    /**
     * Get all registered style groups.
     */
    public function getStyleGroups(): array
    {
        return array_keys($this->style_groups);
    }

    /**
     * Get group configuration.
     */
    public function getGroupConfig(string $group_name, string $type): ?array
    {
        if ($type === 'scripts') {
            return $this->script_groups[$group_name] ?? null;
        }

        if ($type === 'styles') {
            return $this->style_groups[$group_name] ?? null;
        }

        return null;
    }

    /**
     * Remove a group.
     */
    public function removeGroup(string $group_name, string $type = 'both'): static
    {
        if ($type === 'both' || $type === 'scripts') {
            unset($this->script_groups[$group_name]);
        }

        if ($type === 'both' || $type === 'styles') {
            unset($this->style_groups[$group_name]);
        }

        return $this;
    }

    /**
     * Get the application slug.
     */
    public function getAppSlug(): string
    {
        return $this->app_slug;
    }

    /**
     * Get the base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->base_url;
    }

    /**
     * Get the version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get the config instance if available.
     */
    public function getConfig(): ?Config
    {
        return $this->config;
    }

    /**
     * Reset enqueued groups (useful for testing).
     */
    public function resetEnqueuedGroups(): static
    {
        $this->enqueued_groups = [];
        return $this;
    }

    /**
     * Get all enqueued groups.
     */
    public function getEnqueuedGroups(): array
    {
        return array_keys($this->enqueued_groups);
    }

    /**
     * Enhanced registerHooks with global localization support.
     */
    protected function registerHooks(): void
    {
        if ($this->hooks_registered) {
            return;
        }

        // Only register auto-enqueue hooks if enabled
        if ($this->auto_enqueue_groups) {
            add_action('init', [$this, 'setupGroupHooks'], 10);
        }

        // Always register individual assets (they're manually added)
        add_action('init', [$this, 'setupIndividualAssets'], 5);

        // Global localization output
        add_action('wp_enqueue_scripts', [self::class, 'outputGlobalLocalization'], 999);
        add_action('admin_enqueue_scripts', [self::class, 'outputGlobalLocalization'], 999);

        $this->hooks_registered = true;
    }

    /**
     * Setup hooks for individual assets.
     */
    public function setupIndividualAssets(): void
    {
        foreach ($this->individual_scripts as $handle => $config) {
            add_action($config['hook'], function () use ($handle, $config) {
                $this->enqueueIndividualScript($handle, $config);
            }, $config['priority']);
        }

        foreach ($this->individual_styles as $handle => $config) {
            add_action($config['hook'], function () use ($handle, $config) {
                $this->enqueueIndividualStyle($handle, $config);
            }, $config['priority']);
        }
    }

    /**
     * Enhanced setupGroupHooks - only for auto-enqueue groups.
     */
    public function setupGroupHooks(): void
    {
        foreach ($this->script_groups as $group_name => $group_data) {
            $config = $group_data['config'];
            if ($config['auto_enqueue']) {
                add_action($config['hook'], function () use ($group_name) {
                    $this->enqueueScriptGroup($group_name);
                }, $config['priority']);
            }
        }

        foreach ($this->style_groups as $group_name => $group_data) {
            $config = $group_data['config'];
            if ($config['auto_enqueue']) {
                add_action($config['hook'], function () use ($group_name) {
                    $this->enqueueStyleGroup($group_name);
                }, $config['priority']);
            }
        }
    }

    /**
     * Enqueue a script group.
     */
    protected function enqueueScriptGroup(string $group_name): void
    {
        if (!isset($this->script_groups[$group_name])) {
            return;
        }

        $group_data = $this->script_groups[$group_name];


        if (!$this->shouldLoadGroup($group_data['config'])) {
            return;
        }

        foreach ($group_data['scripts'] as $handle => $config) {
            $this->enqueueIndividualScript($handle, $config);
        }
    }

    /**
     * Enqueue a style group.
     */
    protected function enqueueStyleGroup(string $group_name): void
    {
        if (!isset($this->style_groups[$group_name])) {
            return;
        }

        $group_data = $this->style_groups[$group_name];

        if (!$this->shouldLoadGroup($group_data['config'])) {
            return;
        }

        foreach ($group_data['styles'] as $handle => $config) {
            $this->enqueueIndividualStyle($handle, $config);
        }
    }

    /**
     * Enhanced enqueueIndividualScript with global localization.
     */
    protected function enqueueIndividualScript(string $handle, array $config): void
    {
        // Check condition
        if ($config['condition'] && is_callable($config['condition'])) {
            if (!call_user_func($config['condition'])) {
                return;
            }
        }
        wp_enqueue_script(
            $handle,
            $config['src'],
            $config['deps'],
            $config['version'],
            $config['in_footer']
        );

        // Add strategy (WP 6.3+)
        if (!empty($config['strategy']) && function_exists('wp_script_add_data')) {
            wp_script_add_data($handle, 'strategy', $config['strategy']);
        }

        // Handle localization
        $this->handleScriptLocalization($handle, $config);

        // Add inline scripts
        $this->addInlineScripts($handle, $config);
    }

    /**
     * Enqueue an individual style.
     */
    protected function enqueueIndividualStyle(string $handle, array $config): void
    {
        // Check condition
        if ($config['condition'] && is_callable($config['condition'])) {
            if (!call_user_func($config['condition'])) {
                return;
            }
        }

        wp_enqueue_style(
            $handle,
            $config['src'],
            $config['deps'],
            $config['version'],
            $config['media']
        );

        // Add inline styles
        if (!empty($config['inline_before'])) {
            wp_add_inline_style($handle, $config['inline_before']);
        }

        if (!empty($config['inline_after'])) {
            wp_add_inline_style($handle, $config['inline_after']);
        }

        if (isset($this->inline_styles[$handle])) {
            foreach ($this->inline_styles[$handle]['before'] as $style) {
                wp_add_inline_style($handle, $style);
            }

            foreach ($this->inline_styles[$handle]['after'] as $style) {
                wp_add_inline_style($handle, $style);
            }
        }
    }

    /**
     * Handle script localization with global data support.
     */
    protected function handleScriptLocalization(string $handle, array $config): void
    {
        // Individual script localization
        if (isset($this->localizations[$handle])) {
            $localize = $this->localizations[$handle];
            wp_localize_script($handle, $localize['object_name'], $localize['data']);
        }

        // Config-based localization
        if (isset($config['localize']) && is_array($config['localize'])) {
            wp_localize_script(
                $handle,
                $config['localize']['object_name'],
                $config['localize']['data']
            );
        }

        // Add to global data
        if (isset($this->localizations[$handle])) {
            $this->addGlobalData([
                $handle => $this->localizations[$handle]['data']
            ]);
        }
    }

    /**
     * Add inline scripts to a handle.
     */
    protected function addInlineScripts(string $handle, array $config): void
    {
        if (!empty($config['inline_before'])) {
            wp_add_inline_script($handle, $config['inline_before'], 'before');
        }

        if (!empty($config['inline_after'])) {
            wp_add_inline_script($handle, $config['inline_after'], 'after');
        }

        if (isset($this->inline_scripts[$handle])) {
            foreach ($this->inline_scripts[$handle]['before'] as $script) {
                wp_add_inline_script($handle, $script, 'before');
            }

            foreach ($this->inline_scripts[$handle]['after'] as $script) {
                wp_add_inline_script($handle, $script, 'after');
            }
        }
    }

    /**
     * Output global localization data.
     */
	public static function outputGlobalLocalization(): void
	{
		if (self::$global_localization_output || empty(self::$global_localization)) {
			return;
		}

		// Build the full data array to be assigned to window.wpToolkit
		$localization_data = [];

		foreach (self::$global_localization as $app_slug => $data) {
			$localization_data[$app_slug] = $data;
		}

		// Encode the entire array once
		$json = wp_json_encode($localization_data);

		// Output the script that assigns it to window.wpToolkit
		$script = sprintf('window.wpToolkit = %s;', $json);

		wp_add_inline_script('jquery', $script, 'before');
		self::$global_localization_output = true;
	}


	/**
     * Parse config or slug parameter and set instance properties.
     */
    protected function parseConfigOrSlug(Config|string $config_or_slug): void
    {
        if ($config_or_slug instanceof Config) {
            $this->config = $config_or_slug;
            $this->app_slug = $config_or_slug->slug;
            $this->text_domain = $config_or_slug->get('text_domain', $config_or_slug->slug);
        } elseif (is_string($config_or_slug)) {
            $this->config = null;
            $this->app_slug = sanitize_key($config_or_slug);
            $this->text_domain = $this->app_slug;
        } else {
            throw new InvalidArgumentException('First parameter must be Config instance or string');
        }

        if (empty($this->app_slug)) {
            throw new InvalidArgumentException('App slug cannot be empty');
        }
    }

    /**
     * Get default base URL.
     */
    protected function getDefaultBaseUrl(): string
    {
        if ($this->config) {
            $plugin_url = $this->config->get('plugin_url');
            if ($plugin_url) {
                return $plugin_url . '/assets';
            }
        }

        return get_template_directory_uri() . '/assets';
    }

    /**
     * Get default base path.
     */
    protected function getDefaultBasePath(): string
    {
        if ($this->config) {
            $plugin_dir = $this->config->get('plugin_dir');
            if ($plugin_dir) {
                return $plugin_dir . '/assets';
            }
        }

        return get_template_directory() . '/assets';
    }

    /**
     * Get default version.
     */
    protected function getDefaultVersion(): string
    {
        if ($this->config) {
            return $this->config->get('version', '1.0.0');
        }

        return wp_get_theme()->get('Version') ?? '1.0.0';
    }

    /**
     * Resolve asset URL from path.
     */
    protected function resolveAssetUrl(string $src): string
    {
        // If it's already a full URL, return as-is
        if (filter_var($src, FILTER_VALIDATE_URL)) {
            return $src;
        }

        // If it starts with /, treat as absolute path from base URL
        if (str_starts_with($src, '/')) {
            return $this->base_url . $src;
        }

        // Relative path
        return $this->base_url . '/' . ltrim($src, '/');
    }
}
