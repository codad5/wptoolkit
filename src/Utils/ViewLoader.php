<?php

/**
 * View Loader Utility
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * ViewLoader utility class for loading template files.
 * 
 * Provides a comprehensive view loading system with:
 * - Multiple template path support
 * - Data injection and variable extraction
 * - Template caching capabilities
 * - Nested view support
 * - Template inheritance and sections
 * - Error handling and debugging
 */
class ViewLoader
{
    /**
     * Default base path for templates.
     */
    private const DEFAULT_BASE_PATH = 'templates';

    /**
     * Registered template paths in order of priority.
     *
     * @var array
     */
    private static array $template_paths = [];

    /**
     * Global data available to all views.
     *
     * @var array
     */
    private static array $global_data = [];

    /**
     * Template cache settings.
     *
     * @var array
     */
    private static array $cache_settings = [
        'enabled' => false,
        'duration' => 3600, // 1 hour
        'group' => 'wptoolkit_views'
    ];

    /**
     * Supported file extensions.
     *
     * @var array
     */
    private static array $extensions = ['.php', '.html', '.htm'];

    /**
     * Template sections for layout inheritance.
     *
     * @var array
     */
    private static array $sections = [];

    /**
     * Current section being captured.
     *
     * @var string|null
     */
    private static ?string $current_section = null;

    /**
     * Load a view file with optional data.
     *
     * @param string $view View path relative to template directories
     * @param array $data Data to be extracted as variables
     * @param bool $echo Whether to echo the output
     * @param string|null $base_path Override base path for this load
     * @param bool $overridable Whether to check for theme overrides
     * @param string $plugin_prefix Plugin prefix for theme override path
     * @return string|false Rendered output or false on failure
     */
	public static function load(string $view, array $data = [], bool $echo = true, ?string $base_path = null, bool $overridable = false, string $plugin_prefix = 'wptoolkit'): string|false
	{
		$template_path = self::resolve_template_path($view, $base_path, $overridable, $plugin_prefix);

        if (!$template_path) {
            return self::handle_template_not_found($view, $echo);
        }

        // Check cache first
        if (self::$cache_settings['enabled']) {
            $cache_key = self::generate_cache_key($template_path, $data);
            $cached_output = Cache::get($cache_key, false, self::$cache_settings['group']);

            if ($cached_output !== false) {
                if ($echo) {
                    echo $cached_output;
                }
                return $cached_output;
            }
        }

        // Merge with global data
        $data = array_merge(self::$global_data, $data);

        // Add helper functions to data
        $data['view'] = new class {
            public static function load(string $view, array $data = []): string
            {
                return ViewLoader::load($view, $data, false) ?: '';
            }

            public static function include(string $view, array $data = []): void
            {
                ViewLoader::load($view, $data, true);
            }

            public static function section(string $name): void
            {
                ViewLoader::start_section($name);
            }

            public static function end_section(): void
            {
                ViewLoader::end_section();
            }

            public static function yield(string $name, string $default = ''): void
            {
                echo ViewLoader::get_section($name, $default);
            }
        };

        // Render the template
        $output = self::render_template($template_path, $data);

        if ($output === false) {
            return self::handle_render_error($view, $echo);
        }

        // Cache the output
        if (self::$cache_settings['enabled']) {
            Cache::set($cache_key, $output, self::$cache_settings['duration'], self::$cache_settings['group']);
        }

        if ($echo) {
            echo $output;
        }

        return $output;
    }

    /**
     * Load a view and return output without echoing.
     *
     * @param string $view View path
     * @param array $data Data for the view
     * @param string|null $base_path Override base path
     * @return string Rendered output
     */
    public static function get(string $view, array $data = [], ?string $base_path = null): string
    {
        return self::load($view, $data, false, $base_path) ?: '';
    }

	/**
	 * Load a view with WordPress theme override support.
	 *
	 * @param string $view View path relative to plugin templates
	 * @param array $data Data to be extracted as variables
	 * @param bool $echo Whether to echo the output
	 * @param string $plugin_prefix Plugin prefix for theme override path
	 * @return string|false Rendered output or false on failure
	 */
	public static function get_overridable(string $view, array $data = [], bool $echo = true, string $plugin_prefix = 'wptoolkit'): string|false
	{
		return self::load($view, $data, $echo, null, true, $plugin_prefix);
	}

    /**
     * Check if a view exists.
     *
     * @param string $view View path
     * @param string|null $base_path Override base path
     * @return bool True if view exists
     */
    public static function exists(string $view, ?string $base_path = null): bool
    {
        return self::resolve_template_path($view, $base_path) !== false;
    }

    /**
     * Add a template path with priority.
     *
     * @param string $path Template path
     * @param int $priority Priority (lower numbers = higher priority)
     * @return void
     */
    public static function add_path(string $path, int $priority = 10): void
    {
        $real_path = realpath($path);

        if (!$real_path) {
            return;
        }

        self::$template_paths[] = [
            'path' => $real_path,
            'priority' => $priority
        ];

        // Sort by priority
        usort(self::$template_paths, fn($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Remove a template path.
     *
     * @param string $path Template path to remove
     * @return void
     */
    public static function remove_path(string $path): void
    {
        $real_path = realpath($path);

        if (!$real_path) {
            return;
        }

        self::$template_paths = array_filter(
            self::$template_paths,
            fn($item) => $item['path'] !== $real_path
        );
    }

    /**
     * Get all registered template paths.
     *
     * @return array Template paths with priorities
     */
    public static function get_paths(): array
    {
        return self::$template_paths;
    }

    /**
     * Clear all template paths.
     *
     * @return void
     */
    public static function clear_paths(): void
    {
        self::$template_paths = [];
    }

    /**
     * Set global data available to all views.
     *
     * @param array $data Global data
     * @return void
     */
    public static function set_global_data(array $data): void
    {
        self::$global_data = array_merge(self::$global_data, $data);
    }

    /**
     * Get global data.
     *
     * @param string|null $key Specific key to get, or null for all data
     * @return mixed Global data
     */
    public static function get_global_data(?string $key = null): mixed
    {
        if ($key === null) {
            return self::$global_data;
        }

        return self::$global_data[$key] ?? null;
    }

    /**
     * Clear global data.
     *
     * @param string|null $key Specific key to clear, or null to clear all
     * @return void
     */
    public static function clear_global_data(?string $key = null): void
    {
        if ($key === null) {
            self::$global_data = [];
        } else {
            unset(self::$global_data[$key]);
        }
    }

    /**
     * Enable template caching.
     *
     * @param int $duration Cache duration in seconds
     * @param string $group Cache group
     * @return void
     */
    public static function enable_cache(int $duration = 3600, string $group = 'wptoolkit_views'): void
    {
        self::$cache_settings = [
            'enabled' => true,
            'duration' => $duration,
            'group' => $group
        ];
    }

    /**
     * Disable template caching.
     *
     * @return void
     */
    public static function disable_cache(): void
    {
        self::$cache_settings['enabled'] = false;
    }

    /**
     * Clear template cache.
     *
     * @return int Number of cache entries cleared
     */
    public static function clear_cache(): int
    {
        return Cache::clear_group(self::$cache_settings['group']);
    }

    /**
     * Start capturing a section.
     *
     * @param string $name Section name
     * @return void
     */
    public static function start_section(string $name): void
    {
        if (self::$current_section !== null) {
            throw new \Exception("Cannot start section '{$name}' while section '" . self::$current_section . "' is active");
        }

        self::$current_section = $name;
        ob_start();
    }

    /**
     * End the current section.
     *
     * @return void
     */
    public static function end_section(): void
    {
        if (self::$current_section === null) {
            throw new \Exception('No active section to end');
        }

        $content = ob_get_clean();
        self::$sections[self::$current_section] = $content;
        self::$current_section = null;
    }

    /**
     * Get a section's content.
     *
     * @param string $name Section name
     * @param string $default Default content if section doesn't exist
     * @return string Section content
     */
    public static function get_section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    /**
     * Check if a section exists.
     *
     * @param string $name Section name
     * @return bool True if section exists
     */
    public static function has_section(string $name): bool
    {
        return isset(self::$sections[$name]);
    }

    /**
     * Clear all sections.
     *
     * @return void
     */
    public static function clear_sections(): void
    {
        self::$sections = [];
        self::$current_section = null;
    }

    /**
     * Load a layout with content sections.
     *
     * @param string $layout Layout template name
     * @param string $content_view Content view to load
     * @param array $data Data for both layout and content
     * @param bool $echo Whether to echo output
     * @return string|false Rendered output
     */
    public static function layout(string $layout, string $content_view, array $data = [], bool $echo = true): string|false
    {
        // Clear previous sections
        self::clear_sections();

        // Load content view first to capture sections
        $content = self::load($content_view, $data, false);

        if ($content === false) {
            return false;
        }

        // Add content to sections
        self::$sections['content'] = $content;

        // Load layout
        return self::load($layout, $data, $echo);
    }

    /**
     * Add supported file extensions.
     *
     * @param array $extensions Array of extensions (with dots)
     * @return void
     */
    public static function add_extensions(array $extensions): void
    {
        foreach ($extensions as $ext) {
            if (!in_array($ext, self::$extensions)) {
                self::$extensions[] = $ext;
            }
        }
    }

    /**
     * Get supported extensions.
     *
     * @return array Supported extensions
     */
    public static function get_extensions(): array
    {
        return self::$extensions;
    }

	/**
	 * Resolve template path from view name.
	 *
	 * @param string $view View name
	 * @param string|null $base_path Override base path
	 * @param bool $overridable Whether to check for theme overrides
	 * @param string $plugin_prefix Plugin prefix for theme override path
	 * @return string|false Resolved path or false if not found
	 */
	private static function resolve_template_path(string $view, ?string $base_path = null, bool $overridable = false, string $plugin_prefix = 'wptoolkit'): string|false
	{
	    $view = ltrim($view, '/');

	    // Check for WordPress theme override first if overridable
	    if ($overridable) {
		    $theme_template = locate_template("{$plugin_prefix}/{$view}");
		    if ($theme_template) {
			    return $theme_template;
		    }

		    // Also check with .php extension if not present
		    if (!pathinfo($view, PATHINFO_EXTENSION)) {
			    $theme_template = locate_template("{$plugin_prefix}/{$view}.php");
			    if ($theme_template) {
				    return $theme_template;
			    }
		    }
	    }
        $search_paths = [];

        // Add override base path first
        if ($base_path) {
            $real_base = realpath($base_path);
            if ($real_base) {
                $search_paths[] = $real_base;
            }
        }

        // Add registered paths
        foreach (self::$template_paths as $path_info) {
            $search_paths[] = $path_info['path'];
        }

        // Add default path if no paths registered
        if (empty(self::$template_paths) && !$base_path) {
            $default_path = realpath(self::DEFAULT_BASE_PATH);
            if ($default_path) {
                $search_paths[] = $default_path;
            }
        }

        // Search in each path
        foreach ($search_paths as $base_path) {
            $template_path = self::find_template_in_path($base_path, $view);
            if ($template_path) {
                return $template_path;
            }
        }

        return false;
    }

    /**
     * Find template in a specific path.
     *
     * @param string $base_path Base path to search in
     * @param string $view View name
     * @return string|false Template path or false if not found
     */
    private static function find_template_in_path(string $base_path, string $view): string|false
    {
        $full_path = $base_path . '/' . $view;

        // If view has extension, check directly
        if (pathinfo($view, PATHINFO_EXTENSION)) {
            if (is_file($full_path)) {
                return $full_path;
            }

            // Check for index file in directory
            if (is_dir($full_path)) {
                $index_path = $full_path . '/index.php';
                if (is_file($index_path)) {
                    return $index_path;
                }
            }

            return false;
        }

        // Try different extensions
        foreach (self::$extensions as $ext) {
            if (is_file($full_path . $ext)) {
                return $full_path . $ext;
            }
        }

        // Check for directory with index file
        if (is_dir($full_path)) {
            foreach (self::$extensions as $ext) {
                $index_path = $full_path . '/index' . $ext;
                if (is_file($index_path)) {
                    return $index_path;
                }
            }
        }

        return false;
    }

    /**
     * Render a template file with data.
     *
     * @param string $template_path Absolute path to template
     * @param array $data Data to extract
     * @return string|false Rendered output or false on error
     */
    private static function render_template(string $template_path, array $data): string|false
    {
        if (!is_readable($template_path)) {
            return false;
        }

        try {
            // Extract data to local scope
            extract($data, EXTR_OVERWRITE);

            // Start output buffering
            ob_start();

            // Include the template
            include $template_path;

            // Get the output
            return ob_get_clean();
        } catch (\Throwable $e) {
            // Clean buffer on error
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            error_log('ViewLoader render error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate cache key for template and data.
     *
     * @param string $template_path Template path
     * @param array $data Template data
     * @return string Cache key
     */
    private static function generate_cache_key(string $template_path, array $data): string
    {
        $key_parts = [
            'template' => $template_path,
            'data' => $data,
            'modified' => filemtime($template_path)
        ];

        return 'view_' . md5(serialize($key_parts));
    }

    /**
     * Handle template not found error.
     *
     * @param string $view View name
     * @param bool $echo Whether to echo error
     * @return false
     */
    private static function handle_template_not_found(string $view, bool $echo): false
    {
        $error_message = "Template not found: {$view}";

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($echo) {
                echo "<div class=\"wptoolkit-error\">{$error_message}</div>";
            }
            error_log("ViewLoader: {$error_message}");
        }

        return false;
    }

    /**
     * Handle template render error.
     *
     * @param string $view View name
     * @param bool $echo Whether to echo error
     * @return false
     */
    private static function handle_render_error(string $view, bool $echo): false
    {
        $error_message = "Error rendering template: {$view}";

        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($echo) {
                echo "<div class=\"wptoolkit-error\">{$error_message}</div>";
            }
            error_log("ViewLoader: {$error_message}");
        }

        return false;
    }
}
