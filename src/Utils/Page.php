<?php

/**
 * Page Helper
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * Page Helper class for managing WordPress admin pages and frontend pages.
 *
 * Provides a clean API for creating admin menu pages, submenu pages,
 * and frontend page routes with automatic capability checking and routing.
 */
class Page
{
    /**
     * Registered menu pages.
     */
    protected static array $menu_pages = [];

    /**
     * Registered submenu pages.
     */
    protected static array $submenu_pages = [];

    /**
     * Registered frontend pages.
     */
    protected static array $frontend_pages = [];

    /**
     * Current page information.
     */
    protected static array $current_page = [];

    /**
     * Initialize the page system.
     *
     * @return bool Success status
     */
    public static function init(): bool
    {
        add_action('admin_menu', [self::class, 'register_admin_pages']);
        add_action('init', [self::class, 'register_frontend_pages']);
        add_action('template_redirect', [self::class, 'handle_frontend_routing']);

        return true;
    }

    /**
     * Add a main menu page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return bool Success status
     */
    public static function add_menu_page(string $slug, array $config): bool
    {
        $defaults = [
            'page_title' => '',
            'menu_title' => '',
            'capability' => 'manage_options',
            'callback' => null,
            'icon' => 'dashicons-admin-generic',
            'position' => null,
        ];

        $slug = sanitize_key($slug);
        self::$menu_pages[$slug] = array_merge($defaults, $config);

        return true;
    }

    /**
     * Add a submenu page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return bool Success status
     */
    public static function add_submenu_page(string $slug, array $config): bool
    {
        $defaults = [
            'parent_slug' => '',
            'page_title' => '',
            'menu_title' => '',
            'capability' => 'manage_options',
            'callback' => null,
        ];

        $slug = sanitize_key($slug);
        self::$submenu_pages[$slug] = array_merge($defaults, $config);

        return true;
    }

    /**
     * Add a frontend page/route.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return bool Success status
     */
    public static function add_frontend_page(string $slug, array $config): bool
    {
        $defaults = [
            'title' => '',
            'template' => null,
            'callback' => null,
            'public' => true,
            'rewrite' => true,
            'query_vars' => [],
            'capability' => null, // null means public access
        ];

        $slug = sanitize_key($slug);
        self::$frontend_pages[$slug] = array_merge($defaults, $config);

        return true;
    }

    /**
     * Register multiple admin pages at once.
     *
     * @param array<string, array<string, mixed>> $pages Pages configuration
     * @return bool Success status
     */
    public static function add_admin_pages(array $pages): bool
    {
        foreach ($pages as $slug => $config) {
            if (isset($config['parent_slug'])) {
                self::add_submenu_page($slug, $config);
            } else {
                self::add_menu_page($slug, $config);
            }
        }
        return true;
    }

    /**
     * Register multiple frontend pages at once.
     *
     * @param array<string, array<string, mixed>> $pages Pages configuration
     * @return bool Success status
     */
    public static function add_frontend_pages(array $pages): bool
    {
        foreach ($pages as $slug => $config) {
            self::add_frontend_page($slug, $config);
        }
        return true;
    }

    /**
     * Get the URL for an admin page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $params Additional URL parameters
     * @return string Page URL
     */
    public static function get_admin_url(string $slug, array $params = []): string
    {
        $base_url = admin_url('admin.php?page=' . sanitize_key($slug));

        if (!empty($params)) {
            $base_url = add_query_arg($params, $base_url);
        }

        return esc_url($base_url);
    }

    /**
     * Get the URL for a frontend page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $params Additional URL parameters
     * @return string Page URL
     */
    public static function get_frontend_url(string $slug, array $params = []): string
    {
        $page_config = self::$frontend_pages[$slug] ?? null;

        if (!$page_config) {
            return home_url('/');
        }

        $base_url = home_url('/' . $slug . '/');

        if (!empty($params)) {
            $base_url = add_query_arg($params, $base_url);
        }

        return esc_url($base_url);
    }

    /**
     * Get current page information.
     *
     * @return array<string, mixed> Current page data
     */
    public static function get_current_page(): array
    {
        if (empty(self::$current_page)) {
            self::detect_current_page();
        }

        return self::$current_page;
    }

    /**
     * Get current page slug.
     *
     * @return string Current page slug
     */
    public static function get_current_page_slug(): string
    {
        $current = self::get_current_page();
        return $current['slug'] ?? '';
    }

    /**
     * Check if current page is a plugin admin page.
     *
     * @param string|null $slug Optional specific page slug to check
     * @return bool Whether current page is a plugin admin page
     */
    public static function is_plugin_admin_page(?string $slug = null): bool
    {
        if (!is_admin()) {
            return false;
        }

        $current = self::get_current_page();
        $is_plugin_page = ($current['type'] ?? '') === 'admin' &&
            in_array($current['slug'] ?? '', array_keys(array_merge(self::$menu_pages, self::$submenu_pages)), true);

        if ($slug !== null) {
            return $is_plugin_page && ($current['slug'] ?? '') === $slug;
        }

        return $is_plugin_page;
    }

    /**
     * Check if current page is a plugin frontend page.
     *
     * @param string|null $slug Optional specific page slug to check
     * @return bool Whether current page is a plugin frontend page
     */
    public static function is_plugin_frontend_page(?string $slug = null): bool
    {
        $current = self::get_current_page();
        $is_plugin_page = ($current['type'] ?? '') === 'frontend' &&
            in_array($current['slug'] ?? '', array_keys(self::$frontend_pages), true);

        if ($slug !== null) {
            return $is_plugin_page && ($current['slug'] ?? '') === $slug;
        }

        return $is_plugin_page;
    }

    /**
     * Get all registered admin page slugs.
     *
     * @return array<string> Admin page slugs
     */
    public static function get_admin_page_slugs(): array
    {
        return array_merge(
            array_keys(self::$menu_pages),
            array_keys(self::$submenu_pages)
        );
    }

    /**
     * Get all registered frontend page slugs.
     *
     * @return array<string> Frontend page slugs
     */
    public static function get_frontend_page_slugs(): array
    {
        return array_keys(self::$frontend_pages);
    }

    /**
     * Render a page using a template or callback.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $data Data to pass to template
     * @return void
     */
    public static function render_page(string $slug, array $data = []): void
    {
        // Check admin pages first
        if (isset(self::$menu_pages[$slug])) {
            self::render_admin_page($slug, self::$menu_pages[$slug], $data);
            return;
        }

        if (isset(self::$submenu_pages[$slug])) {
            self::render_admin_page($slug, self::$submenu_pages[$slug], $data);
            return;
        }

        // Check frontend pages
        if (isset(self::$frontend_pages[$slug])) {
            self::render_frontend_page($slug, self::$frontend_pages[$slug], $data);
            return;
        }

        // Page not found
        if (is_admin()) {
            wp_die(esc_html__('Page not found', 'textdomain'));
        } else {
            wp_safe_redirect(home_url('/'));
            exit;
        }
    }

    /**
     * Add a custom dashboard widget.
     *
     * @param string $widget_id Widget ID
     * @param string $widget_title Widget title
     * @param callable $callback Widget callback
     * @param string $capability Required capability
     * @return bool Success status
     */
    public static function add_dashboard_widget(
        string $widget_id,
        string $widget_title,
        callable $callback,
        string $capability = 'read'
    ): bool {
        add_action('wp_dashboard_setup', function () use ($widget_id, $widget_title, $callback, $capability) {
            if (current_user_can($capability)) {
                wp_add_dashboard_widget(
                    sanitize_key($widget_id),
                    esc_html($widget_title),
                    $callback
                );
            }
        });

        return true;
    }

    /**
     * Register admin pages with WordPress.
     *
     * @return void
     */
    public static function register_admin_pages(): void
    {
        // Register main menu pages
        foreach (self::$menu_pages as $slug => $config) {
            add_menu_page(
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $slug,
                self::get_page_callback($slug, $config),
                $config['icon'],
                $config['position']
            );
        }

        // Register submenu pages
        foreach (self::$submenu_pages as $slug => $config) {
            add_submenu_page(
                $config['parent_slug'],
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $slug,
                self::get_page_callback($slug, $config)
            );
        }
    }

    /**
     * Register frontend pages with WordPress.
     *
     * @return void
     */
    public static function register_frontend_pages(): void
    {
        foreach (self::$frontend_pages as $slug => $config) {
            if ($config['rewrite']) {
                //TODOLIST: Add rewrite rules for frontend pages
                // add_rewrite_rule(
                //     '^' . $slug . '/?,
                //     'index.php?plugin_page=' . $slug,
                //     'top'
                // );

                // add_rewrite_rule(
                //     '^' . $slug . '/(.+)/?,
                //     'index.php?plugin_page=' . $slug . '&plugin_path=$matches[1]',
                //     'top'
                // );
            }

            // Add custom query vars
            foreach ($config['query_vars'] as $var) {
                add_filter('query_vars', function ($vars) use ($var) {
                    $vars[] = $var;
                    return $vars;
                });
            }
        }

        // Add our main query vars
        add_filter('query_vars', function ($vars) {
            $vars[] = 'plugin_page';
            $vars[] = 'plugin_path';
            return $vars;
        });

        // Flush rewrite rules if needed
        if (get_option(self::get_flush_rules_option()) !== '1') {
            flush_rewrite_rules();
            update_option(self::get_flush_rules_option(), '1');
        }
    }

    /**
     * Handle frontend page routing.
     *
     * @return void
     */
    public static function handle_frontend_routing(): void
    {
        $plugin_page = get_query_var('plugin_page');

        if (empty($plugin_page) || !isset(self::$frontend_pages[$plugin_page])) {
            return;
        }

        $config = self::$frontend_pages[$plugin_page];

        // Check capability if required
        if ($config['capability'] && !current_user_can($config['capability'])) {
            wp_safe_redirect(wp_login_url(get_permalink()));
            exit;
        }

        // Set current page info
        self::$current_page = [
            'type' => 'frontend',
            'slug' => $plugin_page,
            'config' => $config,
        ];

        // Handle the request
        self::render_frontend_page($plugin_page, $config);
    }

    /**
     * Detect current page information.
     *
     * @return void
     */
    protected static function detect_current_page(): void
    {
        if (is_admin()) {
            $page = sanitize_text_field($_GET['page'] ?? '');

            if (isset(self::$menu_pages[$page])) {
                self::$current_page = [
                    'type' => 'admin',
                    'slug' => $page,
                    'config' => self::$menu_pages[$page],
                ];
            } elseif (isset(self::$submenu_pages[$page])) {
                self::$current_page = [
                    'type' => 'admin',
                    'slug' => $page,
                    'config' => self::$submenu_pages[$page],
                ];
            }
        } else {
            $plugin_page = get_query_var('plugin_page');

            if ($plugin_page && isset(self::$frontend_pages[$plugin_page])) {
                self::$current_page = [
                    'type' => 'frontend',
                    'slug' => $plugin_page,
                    'config' => self::$frontend_pages[$plugin_page],
                ];
            }
        }
    }

    /**
     * Get page callback function.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return callable|array Page callback
     */
    protected static function get_page_callback(string $slug, array $config)
    {
        $callback = $config['callback'];

        if (is_callable($callback)) {
            return $callback;
        }

        if (is_string($callback) && method_exists(self::class, $callback)) {
            return [self::class, $callback];
        }

        // Default callback
        return function () use ($slug, $config) {
            self::render_page($slug, []);
        };
    }

    /**
     * Render an admin page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @param array<string, mixed> $data Template data
     * @return void
     */
    protected static function render_admin_page(string $slug, array $config, array $data = []): void
    {
        // Check capability
        if (!current_user_can($config['capability'])) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'textdomain'));
        }

        // Set page title
        $GLOBALS['title'] = $config['page_title'];

        // Use callback if provided
        if (isset($config['callback']) && is_callable($config['callback'])) {
            call_user_func($config['callback'], $data);
            return;
        }

        // Use template if provided
        $template = $config['template'] ?? null;
        if ($template) {
            self::load_template($template, $data);
            return;
        }

        // Default admin page content
        printf(
            '<div class="wrap"><h1>%s</h1><p>%s</p></div>',
            esc_html($config['page_title']),
            esc_html__('This page has no content configured.', 'textdomain')
        );
    }

    /**
     * Render a frontend page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @param array<string, mixed> $data Template data
     * @return void
     */
    protected static function render_frontend_page(string $slug, array $config, array $data = []): void
    {
        // Use callback if provided
        if (isset($config['callback']) && is_callable($config['callback'])) {
            call_user_func($config['callback'], $data);
            return;
        }

        // Use template if provided
        $template = $config['template'] ?? null;
        if ($template) {
            self::load_template($template, $data);
            return;
        }

        // Set page title for WordPress
        add_filter('wp_title', function ($title) use ($config) {
            return ($config['title'] ?? 'Plugin Page') . ' | ' . get_bloginfo('name');
        });

        add_filter('document_title_parts', function ($title_parts) use ($config) {
            $title_parts['title'] = $config['title'] ?? 'Plugin Page';
            return $title_parts;
        });

        // Load theme header
        get_header();

        // Default frontend page content
        printf(
            '<div class="plugin-page plugin-page-%s"><div class="container"><h1>%s</h1><p>%s</p></div></div>',
            esc_attr($slug),
            esc_html($config['title'] ?? 'Plugin Page'),
            esc_html__('This page has no content configured.', 'textdomain')
        );

        // Load theme footer
        get_footer();
        exit;
    }

    /**
     * Load a template file.
     *
     * @param string $template Template path
     * @param array<string, mixed> $data Template data
     * @return void
     */
    protected static function load_template(string $template, array $data = []): void
    {
        // Extract data to variables
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        // Try to find template in plugin directory
        $plugin_dir = Config::get('plugin_dir') ?? plugin_dir_path(__FILE__);
        $template_path = $plugin_dir . 'templates/' . ltrim($template, '/');

        if (file_exists($template_path)) {
            include $template_path;
            return;
        }

        // Try theme template override
        $theme_template = locate_template(['plugin-templates/' . basename($template)]);
        if ($theme_template) {
            include $theme_template;
            return;
        }

        // Template not found
        if (is_admin()) {
            printf(
                '<div class="notice notice-error"><p>%s: %s</p></div>',
                esc_html__('Template not found', 'textdomain'),
                esc_html($template)
            );
        }
    }

    /**
     * Get the flush rules option key.
     *
     * @return string Option key
     */
    protected static function get_flush_rules_option(): string
    {
        return (Config::get('slug') ?? 'wp_plugin') . '_flush_rules';
    }
}
