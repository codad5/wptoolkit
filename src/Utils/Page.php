<?php

/**
 * Page Helper
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use InvalidArgumentException;

/**
 * Page Helper class for managing WordPress admin pages and frontend pages.
 *
 * Provides a clean API for creating admin menu pages, submenu pages,
 * and frontend page routes with automatic capability checking and routing.
 * Now fully object-based with dependency injection support.
 */
class Page
{
    /**
     * Registered menu pages.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $menu_pages = [];

    /**
     * Registered submenu pages.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $submenu_pages = [];

    /**
     * Registered frontend pages.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $frontend_pages = [];

    /**
     * Current page information.
     *
     * @var array<string, mixed>
     */
    protected array $current_page = [];

    /**
     * Application slug for identification.
     */
    protected string $app_slug;

    /**
     * Text domain for translations.
     */
    protected string $text_domain;

    /**
     * App name for display.
     */
    protected string $app_name;

    /**
     * Config instance (optional dependency).
     */
    protected ?Config $config = null;

    /**
     * Whether hooks have been registered.
     */
    protected bool $hooks_registered = false;

    /**
     * Template directory path.
     */
    protected string $template_dir;

    /**
     * Constructor for creating a new Page instance.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string|null $template_dir Custom template directory
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(Config|string $config_or_slug, ?string $template_dir = null)
    {
        $this->parseConfigOrSlug($config_or_slug);
        $this->template_dir = $template_dir ?? $this->getDefaultTemplateDir();
        $this->registerHooks();
    }

    /**
     * Static factory method for creating Page instances.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string|null $template_dir Custom template directory
     * @return static New Page instance
     */
    public static function create(Config|string $config_or_slug, ?string $template_dir = null): static
    {
        return new static($config_or_slug, $template_dir);
    }

    /**
     * Add a main menu page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return static For method chaining
     */
    public function addMenuPage(string $slug, array $config): static
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
        $this->menu_pages[$slug] = array_merge($defaults, $config);

        return $this;
    }

    /**
     * Add a submenu page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return static For method chaining
     */
    public function addSubmenuPage(string $slug, array $config): static
    {
        $defaults = [
            'parent_slug' => '',
            'page_title' => '',
            'menu_title' => '',
            'capability' => 'manage_options',
            'callback' => null,
        ];

        $slug = sanitize_key($slug);
        $this->submenu_pages[$slug] = array_merge($defaults, $config);

        return $this;
    }

    /**
     * Add a frontend page/route.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return static For method chaining
     */
    public function addFrontendPage(string $slug, array $config): static
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
        $this->frontend_pages[$slug] = array_merge($defaults, $config);

        return $this;
    }

    /**
     * Register multiple admin pages at once.
     *
     * @param array<string, array<string, mixed>> $pages Pages configuration
     * @return static For method chaining
     */
    public function addAdminPages(array $pages): static
    {
        foreach ($pages as $slug => $config) {
            if (isset($config['parent_slug'])) {
                $this->addSubmenuPage($slug, $config);
            } else {
                $this->addMenuPage($slug, $config);
            }
        }
        return $this;
    }

    /**
     * Register multiple frontend pages at once.
     *
     * @param array<string, array<string, mixed>> $pages Pages configuration
     * @return static For method chaining
     */
    public function addFrontendPages(array $pages): static
    {
        foreach ($pages as $slug => $config) {
            $this->addFrontendPage($slug, $config);
        }
        return $this;
    }

    /**
     * Get the URL for an admin page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $params Additional URL parameters
     * @return string Page URL
     */
    public function getAdminUrl(string $slug, array $params = []): string
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
    public function getFrontendUrl(string $slug, array $params = []): string
    {
        $page_config = $this->frontend_pages[$slug] ?? null;

        if (!$page_config) {
            return home_url('/');
        }

        $base_url = home_url('/' . $this->app_slug . '/' . $slug . '/');

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
    public function getCurrentPage(): array
    {
        if (empty($this->current_page)) {
            $this->detectCurrentPage();
        }

        return $this->current_page;
    }

    /**
     * Get current page slug.
     *
     * @return string Current page slug
     */
    public function getCurrentPageSlug(): string
    {
        $current = $this->getCurrentPage();
        return $current['slug'] ?? '';
    }

    /**
     * Check if current page is a plugin admin page.
     *
     * @param string|null $slug Optional specific page slug to check
     * @return bool Whether current page is a plugin admin page
     */
    public function isPluginAdminPage(?string $slug = null): bool
    {
        if (!is_admin()) {
            return false;
        }

        $current = $this->getCurrentPage();
        $is_plugin_page = ($current['type'] ?? '') === 'admin' &&
            in_array($current['slug'] ?? '', array_keys(array_merge($this->menu_pages, $this->submenu_pages)), true);

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
    public function isPluginFrontendPage(?string $slug = null): bool
    {
        $current = $this->getCurrentPage();
        $is_plugin_page = ($current['type'] ?? '') === 'frontend' &&
            in_array($current['slug'] ?? '', array_keys($this->frontend_pages), true);

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
    public function getAdminPageSlugs(): array
    {
        return array_merge(
            array_keys($this->menu_pages),
            array_keys($this->submenu_pages)
        );
    }

    /**
     * Get all registered frontend page slugs.
     *
     * @return array<string> Frontend page slugs
     */
    public function getFrontendPageSlugs(): array
    {
        return array_keys($this->frontend_pages);
    }

    /**
     * Render a page using a template or callback.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $data Data to pass to template
     * @return void
     */
    public function renderPage(string $slug, array $data = []): void
    {
        // Check admin pages first
        if (isset($this->menu_pages[$slug])) {
            $this->renderAdminPage($slug, $this->menu_pages[$slug], $data);
            return;
        }

        if (isset($this->submenu_pages[$slug])) {
            $this->renderAdminPage($slug, $this->submenu_pages[$slug], $data);
            return;
        }

        // Check frontend pages
        if (isset($this->frontend_pages[$slug])) {
            $this->renderFrontendPage($slug, $this->frontend_pages[$slug], $data);
            return;
        }

        // Page not found
        if (is_admin()) {
            wp_die(esc_html__('Page not found', $this->text_domain));
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
     * @return static For method chaining
     */
    public function addDashboardWidget(
        string $widget_id,
        string $widget_title,
        callable $callback,
        string $capability = 'read'
    ): static {
        add_action('wp_dashboard_setup', function () use ($widget_id, $widget_title, $callback, $capability) {
            if (current_user_can($capability)) {
                wp_add_dashboard_widget(
                    sanitize_key($this->app_slug . '_' . $widget_id),
                    esc_html($widget_title),
                    $callback
                );
            }
        });

        return $this;
    }

    /**
     * Set custom template directory.
     *
     * @param string $template_dir Template directory path
     * @return static For method chaining
     */
    public function setTemplateDirectory(string $template_dir): static
    {
        $this->template_dir = $template_dir;
        return $this;
    }

    /**
     * Get the application slug.
     *
     * @return string Application slug
     */
    public function getAppSlug(): string
    {
        return $this->app_slug;
    }

    /**
     * Get the text domain.
     *
     * @return string Text domain
     */
    public function getTextDomain(): string
    {
        return $this->text_domain;
    }

    /**
     * Get the app name.
     *
     * @return string App name
     */
    public function getAppName(): string
    {
        return $this->app_name;
    }

    /**
     * Get the template directory.
     *
     * @return string Template directory path
     */
    public function getTemplateDirectory(): string
    {
        return $this->template_dir;
    }

    /**
     * Get the config instance if available.
     *
     * @return Config|null Config instance or null
     */
    public function getConfig(): ?Config
    {
        return $this->config;
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    protected function registerHooks(): void
    {
        if ($this->hooks_registered) {
            return;
        }

        add_action('admin_menu', [$this, 'registerAdminPages']);
        add_action('init', [$this, 'registerFrontendPages']);
        add_action('template_redirect', [$this, 'handleFrontendRouting']);

        $this->hooks_registered = true;
    }

    /**
     * Register admin pages with WordPress.
     *
     * @return void
     */
    public function registerAdminPages(): void
    {
        // Register main menu pages
        foreach ($this->menu_pages as $slug => $config) {
            add_menu_page(
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $this->app_slug . '_' . $slug,
                $this->getPageCallback($slug, $config),
                $config['icon'],
                $config['position']
            );
        }

        // Register submenu pages
        foreach ($this->submenu_pages as $slug => $config) {
            $parent_slug = $config['parent_slug'];

            // If parent_slug doesn't include app prefix, add it
            if (!str_contains($parent_slug ?? '', $this->app_slug . '_')) {
                $parent_slug = $this->app_slug . '_' . $parent_slug;
            }

            add_submenu_page(
                $parent_slug,
                $config['page_title'],
                $config['menu_title'],
                $config['capability'],
                $this->app_slug . '_' . $slug,
                $this->getPageCallback($slug, $config)
            );
        }
    }

    /**
     * Register frontend pages with WordPress.
     *
     * @return void
     */
    public function registerFrontendPages(): void
    {
        foreach ($this->frontend_pages as $slug => $config) {
            if ($config['rewrite']) {
                // Add rewrite rules for frontend pages
                add_rewrite_rule(
                    '^' . $this->app_slug . '/' . $slug . '/?$',
                    'index.php?' . $this->app_slug . '_page=' . $slug,
                    'top'
                );

                add_rewrite_rule(
                    '^' . $this->app_slug . '/' . $slug . '/(.+)/?$',
                    'index.php?' . $this->app_slug . '_page=' . $slug . '&' . $this->app_slug . '_path=$matches[1]',
                    'top'
                );
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
            $vars[] = $this->app_slug . '_page';
            $vars[] = $this->app_slug . '_path';
            return $vars;
        });

        // Flush rewrite rules if needed
        if (get_option($this->getFlushRulesOption()) !== '1') {
            flush_rewrite_rules();
            update_option($this->getFlushRulesOption(), '1');
        }
    }

    /**
     * Handle frontend page routing.
     *
     * @return void
     */
    public function handleFrontendRouting(): void
    {
        $plugin_page = get_query_var($this->app_slug . '_page');

        if (empty($plugin_page) || !isset($this->frontend_pages[$plugin_page])) {
            return;
        }

        $config = $this->frontend_pages[$plugin_page];

        // Check capability if required
        if ($config['capability'] && !current_user_can($config['capability'])) {
            wp_safe_redirect(wp_login_url(get_permalink()));
            exit;
        }

        // Set current page info
        $this->current_page = [
            'type' => 'frontend',
            'slug' => $plugin_page,
            'config' => $config,
        ];

        // Handle the request
        $this->renderFrontendPage($plugin_page, $config);
    }

    /**
     * Parse config or slug parameter and set instance properties.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    protected function parseConfigOrSlug(Config|string $config_or_slug): void
    {
        if ($config_or_slug instanceof Config) {
            $this->config = $config_or_slug;
            $this->app_slug = $config_or_slug->slug;
            $this->text_domain = $config_or_slug->get('text_domain', $config_or_slug->slug);
            $this->app_name = $config_or_slug->get('name', ucfirst(str_replace(['-', '_'], ' ', $config_or_slug->slug)));
        } elseif (is_string($config_or_slug)) {
            $this->config = null;
            $this->app_slug = sanitize_key($config_or_slug);
            $this->text_domain = $this->app_slug;
            $this->app_name = ucfirst(str_replace(['-', '_'], ' ', $this->app_slug));
        } else {
            throw new InvalidArgumentException('First parameter must be Config instance or string');
        }

        if (empty($this->app_slug)) {
            throw new InvalidArgumentException('App slug cannot be empty');
        }
    }

    /**
     * Get default template directory.
     *
     * @return string Default template directory path
     */
    protected function getDefaultTemplateDir(): string
    {
        if ($this->config) {
            $plugin_dir = $this->config->get('plugin_dir');
            if ($plugin_dir) {
                return $plugin_dir . '/templates';
            }
        }

        // Fallback to current directory
        return dirname(__FILE__) . '/templates';
    }

    /**
     * Detect current page information.
     *
     * @return void
     */
    protected function detectCurrentPage(): void
    {
        if (is_admin()) {
            $page = sanitize_text_field($_GET['page'] ?? '');

            // Remove app prefix to get clean slug
            $clean_page = str_replace($this->app_slug . '_', '', $page);

            if (isset($this->menu_pages[$clean_page])) {
                $this->current_page = [
                    'type' => 'admin',
                    'slug' => $clean_page,
                    'config' => $this->menu_pages[$clean_page],
                ];
            } elseif (isset($this->submenu_pages[$clean_page])) {
                $this->current_page = [
                    'type' => 'admin',
                    'slug' => $clean_page,
                    'config' => $this->submenu_pages[$clean_page],
                ];
            }
        } else {
            $plugin_page = get_query_var($this->app_slug . '_page');

            if ($plugin_page && isset($this->frontend_pages[$plugin_page])) {
                $this->current_page = [
                    'type' => 'frontend',
                    'slug' => $plugin_page,
                    'config' => $this->frontend_pages[$plugin_page],
                ];
            }
        }
    }

    /**
     * Get page callback function.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return callable Page callback
     */
    protected function getPageCallback(string $slug, array $config): callable
    {
        $callback = $config['callback'];

        if (is_callable($callback)) {
            return $callback;
        }

        if (is_string($callback) && method_exists($this, $callback)) {
            return [$this, $callback];
        }

        // Default callback
        return function () use ($slug, $config) {
            $this->renderPage($slug, []);
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
    protected function renderAdminPage(string $slug, array $config, array $data = []): void
    {
        // Check capability
        if (!current_user_can($config['capability'])) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', $this->text_domain));
        }

        // Set page title
        $GLOBALS['title'] = $config['page_title'];

        // Add app-specific data
        $data = array_merge($data, [
            'app_slug' => $this->app_slug,
            'app_name' => $this->app_name,
            'page_slug' => $slug,
            'page_config' => $config,
        ]);

        // Use callback if provided
        if (isset($config['callback']) && is_callable($config['callback'])) {
            call_user_func($config['callback'], $data);
            return;
        }

        // Use template if provided
        $template = $config['template'] ?? null;
        if ($template) {
            $this->loadTemplate($template, $data);
            return;
        }

        // Default admin page content
        printf(
            '<div class="wrap %s-page"><h1>%s</h1><p>%s</p></div>',
            esc_attr($this->app_slug),
            esc_html($config['page_title']),
            esc_html__('This page has no content configured.', $this->text_domain)
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
    protected function renderFrontendPage(string $slug, array $config, array $data = []): void
    {
        // Add app-specific data
        $data = array_merge($data, [
            'app_slug' => $this->app_slug,
            'app_name' => $this->app_name,
            'page_slug' => $slug,
            'page_config' => $config,
            'page_path' => get_query_var($this->app_slug . '_path', ''),
        ]);

        // Use callback if provided
        if (isset($config['callback']) && is_callable($config['callback'])) {
            call_user_func($config['callback'], $data);
            return;
        }

        // Use template if provided
        $template = $config['template'] ?? null;
        if ($template) {
            $this->loadTemplate($template, $data);
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
            '<div class="plugin-page %s-page %s-page-%s"><div class="container"><h1>%s</h1><p>%s</p></div></div>',
            esc_attr($this->app_slug),
            esc_attr($this->app_slug),
            esc_attr($slug),
            esc_html($config['title'] ?? 'Plugin Page'),
            esc_html__('This page has no content configured.', $this->text_domain)
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
    protected function loadTemplate(string $template, array $data = []): void
    {
        // Extract data to variables
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        // Try to find template in plugin directory
        $template_path = $this->template_dir . '/' . ltrim($template, '/');

        if (file_exists($template_path)) {
            include $template_path;
            return;
        }

        // Try theme template override
        $theme_template = locate_template([
            $this->app_slug . '-templates/' . basename($template),
            'plugin-templates/' . basename($template)
        ]);

        if ($theme_template) {
            include $theme_template;
            return;
        }

        // Template not found
        if (is_admin()) {
            printf(
                '<div class="notice notice-error"><p>%s: %s</p></div>',
                esc_html__('Template not found', $this->text_domain),
                esc_html($template)
            );
        } else {
            printf(
                '<div class="template-error"><p>%s: %s</p></div>',
                esc_html__('Template not found', $this->text_domain),
                esc_html($template)
            );
        }
    }

    /**
     * Get the flush rules option key.
     *
     * @return string Option key
     */
    protected function getFlushRulesOption(): string
    {
        return $this->app_slug . '_flush_rules';
    }
}
