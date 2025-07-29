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

use Codad5\WPToolkit\DB\Model;
use InvalidArgumentException;

/**
 * Page Helper class for managing WordPress admin pages and frontend pages.
 *
 * Provides a clean API for creating admin menu pages, submenu pages,
 * and frontend page routes with automatic capability checking and routing.
 * Now fully object-based with dependency injection support.
 */
final class Page
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
	 * Asset manager instance for handling scripts and styles.
	 */
	protected ?EnqueueManager $asset_manager = null;

	/**
	 * Default asset groups for different page types.
	 *
	 * @var array<string, array<string, string[]>>
	 */
	protected array $default_asset_groups = [
		'admin' => [
			'groups' => [],
			'handles' => []
		],
		'frontend' => [
			'groups' => [],
			'handles' => []
		]
	];

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
        return new Page($config_or_slug, $template_dir);
    }

	/**
	 * Add a main menu page with Model support.
	 *
	 * @param string|Model $slug_or_model Page slug or Model instance
	 * @param array<string, mixed> $config Page configuration
	 *
	 * @return static For method chaining
	 * @throws \Exception
	 */
    public function addMenuPage(string|Model $slug_or_model, array $config): static
    {
        $slug_data = $this->resolveSlugOrModel($slug_or_model, $config);

        $defaults = [
            'page_title' => '',
            'menu_title' => '',
            'capability' => 'manage_options',
            'callback' => null,
            'icon' => 'dashicons-admin-generic',
            'position' => null,
        ];

        $this->menu_pages[$slug_data['key']] = array_merge($defaults, $slug_data['config']);

        return $this;
    }

	/**
	 * Add a submenu page with Model support.
	 *
	 * @param string|Model $slug_or_model Page slug or Model instance
	 * @param array<string, mixed> $config Page configuration
	 *
	 * @return static For method chaining
	 * @throws \Exception
	 */
    public function addSubmenuPage(string|Model $slug_or_model, array $config): static
    {
        $slug_data = $this->resolveSlugOrModel($slug_or_model, $config);

        $defaults = [
            'parent_slug' => '',
            'page_title' => '',
            'menu_title' => '',
            'capability' => 'manage_options',
            'callback' => null,
        ];

        $this->submenu_pages[$slug_data['key']] = array_merge($defaults, $slug_data['config']);

        return $this;
    }

    /**
     * Resolve slug or model parameter into standardized format.
     *
     * @param string|Model $slug_or_model Page slug or Model instance
     * @param array<string, mixed> $config Page configuration
     * @return array{key: string, config: array<string, mixed>} Resolved data
     * @throws InvalidArgumentException|\Exception If parameters are invalid
     */
	protected function resolveSlugOrModel(string|Model $slug_or_model, array $config): array
	{
		if (is_string($slug_or_model)) {
			return [
				'key' => sanitize_key($slug_or_model),
				'config' => $this->mergeAssetConfig($config, 'admin')
			];
		}

		if ($slug_or_model instanceof Model) {
			$post_type = $slug_or_model::get_instance()->get_post_type();

			$model_config = $this->generateModelConfig($slug_or_model, $post_type);
			return [
				'key' => "edit.php?post_type={$post_type}",
				'config' => array_merge(
					$model_config,
					$this->mergeAssetConfig($config, 'admin')
				)
			];
		}

		throw new InvalidArgumentException('First parameter must be string or Model instance');
	}

	/**
	 * Merge asset configuration with page config.
	 *
	 * @param array $config Page configuration
	 * @param string $page_type Page type (admin/frontend)
	 * @return array Merged configuration
	 */
	protected function mergeAssetConfig(array $config, string $page_type): array
	{
		// Add default asset groups if not specified
		if (!isset($config['asset_groups']) && !isset($config['asset_handles'])) {
			$defaults = $this->default_asset_groups[$page_type] ?? [];

			if (!empty($defaults['groups'])) {
				$config['asset_groups'] = $defaults['groups'];
			}

			if (!empty($defaults['handles'])) {
				$config['asset_handles'] = $defaults['handles'];
			}
		}

		return $config;
	}

	/**
	 * Enqueue assets for a page configuration.
	 *
	 * @param array $page_config Page configuration
	 * @return void
	 */
	protected function enqueuePageAssets(array $page_config): void
	{
		if (!$this->hasAssetManager()) {
			return;
		}

		// Enqueue asset groups
		if (isset($page_config['asset_groups']) && is_array($page_config['asset_groups'])) {
			foreach ($page_config['asset_groups'] as $group_name) {
				$this->asset_manager->enqueueGroup($group_name);
			}
		}

		// Enqueue individual handles
		if (isset($page_config['asset_handles']) && is_array($page_config['asset_handles'])) {
			$this->asset_manager->enqueueByHandles($page_config['asset_handles']);
		}

		// Single group support (backward compatibility)
		if (isset($page_config['asset_group']) && is_string($page_config['asset_group'])) {
			$this->asset_manager->enqueueGroup($page_config['asset_group']);
		}

		// Single handle support (backward compatibility)
		if (isset($page_config['asset_handle']) && is_string($page_config['asset_handle'])) {
			$this->asset_manager->enqueueByHandles([$page_config['asset_handle']]);
		}
	}


	/**
     * Generate configuration from Model instance.
     *
     * @param Model $model Model instance
     * @param string $post_type Post type
     * @return array<string, mixed> Generated configuration
     */
    protected function generateModelConfig(Model $model, string $post_type): array
    {
        // Get post type object for labels
        $post_type_obj = get_post_type_object($post_type);

        if (!$post_type_obj) {
            // Fallback to basic configuration
            $singular = ucfirst(str_replace(['_', '-'], ' ', $post_type));
            $plural = $singular . 's';
        } else {
            $singular = $post_type_obj->labels->singular_name ?? ucfirst($post_type);
            $plural = $post_type_obj->labels->name ?? $singular . 's';
        }

        return [
            'page_title' => sprintf(__('All %s', $this->text_domain), $plural),
            'menu_title' => $plural,
            'capability' => 'manage_options',
            'callback' => false, // WordPress handles this automatically for post type pages
            'icon' => $post_type_obj->menu_icon ?? 'dashicons-admin-post',
            'position' => $post_type_obj->menu_position ?? null,
            'is_model_page' => true,
            'post_type' => $post_type,
            'model_instance' => $model,
            'menu_callback' => null, // Custom callback for menu customization
        ];
    }


    /**
     * Register model-based menu page.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $config Page configuration
     * @return void
     */
    protected function registerModelMenuPage(string $slug, array $config): void
    {
        // For model menu pages, we need to customize the existing WordPress post type menu
        add_action('admin_menu', function () use ($slug, $config) {
            global $menu, $submenu;

            $post_type = $config['post_type'];

            // Find the existing menu item for this post type
            $menu_slug = "edit.php?post_type={$post_type}";
            $menu_index = $this->findMenuIndex($menu_slug);

            if ($menu_index !== false) {
                // Customize existing menu item
                if (isset($config['menu_title'])) {
                    $menu[$menu_index][0] = $config['menu_title'];
                }

                if (isset($config['icon'])) {
                    $menu[$menu_index][6] = $config['icon'];
                }

                if (isset($config['position'])) {
                    // Move menu item to new position
                    $menu_item = $menu[$menu_index];
                    unset($menu[$menu_index]);
                    $menu[$config['position']] = $menu_item;
                }

                // Execute custom callback if provided
                if (isset($config['menu_callback']) && is_callable($config['menu_callback'])) {
                    call_user_func($config['menu_callback'], $menu_slug, $config);
                }
            } else {
                // If WordPress hasn't created the menu yet, create it ourselves
                add_menu_page(
                    $config['page_title'],
                    $config['menu_title'],
                    $config['capability'],
                    $menu_slug,
                    '', // No callback needed for post type pages
                    $config['icon'],
                    $config['position']
                );
            }
        }, 100); // Late priority to run after WordPress creates post type menus
    }



    /**
     * Find the index of a menu item in the global $menu array.
     *
     * @param string $menu_slug Menu slug to find
     * @return int|false Menu index or false if not found
     */
    protected function findMenuIndex(string $menu_slug): int|false
    {
        global $menu;

        if (!is_array($menu)) {
            return false;
        }

        foreach ($menu as $index => $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === $menu_slug) {
                return $index;
            }
        }

        return false;
    }





	public function addFrontendPage(string $slug, array $config): static
	{
		$defaults = [
			'title' => '',
			'template' => null,
			'callback' => null,
			'public' => true,
			'rewrite' => true,
			'query_vars' => [],
			'capability' => null,
			'path' => null, // Custom path override
			'use_app_prefix' => true, // Whether to use app_slug prefix
			'regex' => null, // For dynamic routing
			'query_mapping' => [], // For extracting values from regex matches
		];

		$slug = sanitize_key($slug);
		$this->frontend_pages[$slug] = array_merge(
			$defaults,
			$this->mergeAssetConfig($config, 'frontend')
		);

		return $this;
	}


	/**
	 * Register multiple admin pages at once.
	 *
	 * @param array<string, array<string, mixed>> $pages Pages configuration
	 *
	 * @return static For method chaining
	 * @throws \Exception
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
     * Enhanced URL generation with Model support.
     *
     * @param string $slug Page slug
     * @param array<string, mixed> $params Additional URL parameters
     * @return string Page URL
     */
    public function getAdminUrl(string $slug, array $params = []): string
    {
        // Check if it's a model page
        $page_config = $this->menu_pages[$slug] ?? $this->submenu_pages[$slug] ?? null;

        if ($page_config && ($page_config['is_model_page'] ?? false)) {
            // For model pages, use the direct slug (edit.php?post_type=...)
            $base_url = admin_url($slug);
        } else {
            // Regular page
            $base_url = admin_url('admin.php?page=' . sanitize_key($slug));
        }

        if (!empty($params)) {
            $base_url = add_query_arg($params, $base_url);
        }

        return esc_url($base_url);
    }

    // a method to get model url
    /**
     * Get the URL for a model page.
     *
     * @param Model $model_slug Model slug
     * @param array<string, mixed> $params Additional URL parameters
     * @return string Model page URL
     */
    public function getModelUrl(Model $model, array $params = []): string
    {
        $post_type = $model::get_instance()->get_post_type();
        $base_url = admin_url('edit.php?post_type=' . sanitize_key($post_type));

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
	    add_filter('template_include', [$this, 'handleFrontendTemplateInclude']);

        $this->hooks_registered = true;
    }

    /**
     * Enhanced admin page registration with Model support.
     *
     * @return void
     */
    public function registerAdminPages(): void
    {
        // Register main menu pages
        foreach ($this->menu_pages as $slug => $config) {
            if ($config['is_model_page'] ?? false) {
                // For model pages, we don't add_menu_page since WordPress handles post type menus
                // But we can still hook into the menu system if needed
                $this->registerModelMenuPage($slug, $config);
            } else {
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
                ($config['is_model_page'] ?? false) ? $slug : $this->app_slug . '_' . $slug,
                $this->getPageCallback($slug, $config),
                $config['position'] ?? null
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
				$this->addFrontendRewriteRule($slug, $config);
			}

			// Add custom query vars
			foreach ($config['query_vars'] as $var) {
				add_filter('query_vars', function ($vars) use ($var) {
					if (!in_array($var, $vars)) {
						$vars[] = $var;
					}
					return $vars;
				});
			}

			// Add query vars from regex mapping
			if (!empty($config['query_mapping'])) {
				foreach (array_keys($config['query_mapping']) as $var) {
					add_filter('query_vars', function ($vars) use ($var) {
						if (!in_array($var, $vars)) {
							$vars[] = $var;
						}
						return $vars;
					});
				}
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
	 * Add rewrite rule for a frontend page.
	 *
	 * @param string $slug Page slug
	 * @param array $config Page configuration
	 * @return void
	 */
	protected function addFrontendRewriteRule(string $slug, array $config): void
	{
		$query_parts = [];

		// Handle custom regex patterns (dynamic routing)
		if (!empty($config['regex'])) {
			$regex = $config['regex'];

			// Add main page identifier
			$query_parts[] = $this->app_slug . '_page=' . $slug;

			// Add regex-based query mappings
			if (!empty($config['query_mapping'])) {
				foreach ($config['query_mapping'] as $var => $match_index) {
					$query_parts[] = $var . '=' . $match_index;
				}
			}

			$query_string = implode('&', $query_parts);
			add_rewrite_rule($regex, 'index.php?' . $query_string, 'top');

			return;
		}

		// Handle static routing
		$path = $this->getFrontendPagePath($slug, $config);

		// Simple page rule
		$query_parts[] = $this->app_slug . '_page=' . $slug;
		$simple_query = 'index.php?' . implode('&', $query_parts);

		add_rewrite_rule(
			'^' . $path . '/?$',
			$simple_query,
			'top'
		);

		// Page with additional path segments
		add_rewrite_rule(
			'^' . $path . '/(.+)/?$',
			$simple_query . '&' . $this->app_slug . '_path=$matches[1]',
			'top'
		);
	}


	/**
	 * Get the URL path for a frontend page.
	 *
	 * @param string $slug Page slug
	 * @param array $config Page configuration
	 * @return string URL path
	 */
	protected function getFrontendPagePath(string $slug, array $config): string
	{
		// If custom path is provided, use it
		if (!empty($config['path'])) {
			return trim($config['path'], '/');
		}

		// If use_app_prefix is false, use only the slug
		if ($config['use_app_prefix'] === false) {
			return $slug;
		}

		// Default: app_slug/page_slug
		return $this->app_slug . '/' . $slug;
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
	 * Handle frontend template inclusion.
	 *
	 * @param string $template Original template
	 * @return string Template to use
	 */
	public function handleFrontendTemplateInclude(string $template): string
	{
		$plugin_page = get_query_var($this->app_slug . '_page');

		if (empty($plugin_page) || !isset($this->frontend_pages[$plugin_page])) {
			return $template; // Not our page, continue with normal template
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

		// For regex-based pages, populate query vars from matches
		if (!empty($config['regex']) && !empty($config['query_mapping'])) {
			global $wp;
			$request_path = $wp->request;

			if (preg_match('#^' . trim($config['regex'], '^$') . '#', $request_path, $matches)) {
				foreach ($config['query_mapping'] as $var => $match_index) {
					if (isset($matches[(int)str_replace('$matches[', '', str_replace(']', '', $match_index))])) {
						$GLOBALS['wp_query']->set($var, $matches[(int)str_replace('$matches[', '', str_replace(']', '', $match_index))]);
					}
				}
			}
		}

		// Handle the request and return custom template
		return $this->getFrontendTemplate($plugin_page, $config);
	}

	/**
	 * Get frontend template for rendering.
	 *
	 * @param string $slug Page slug
	 * @param array $config Page configuration
	 * @return string Template path
	 */
	protected function getFrontendTemplate(string $slug, array $config): string
	{
		// Enqueue page assets
		$this->enqueuePageAssets($config);

		$data = [
			'app_slug' => $this->app_slug,
			'app_name' => $this->app_name,
			'page_slug' => $slug,
			'page_config' => $config,
			'page_path' => get_query_var($this->app_slug . '_path', ''),
		];

		// Add regex query vars to data
		if (!empty($config['query_mapping'])) {
			foreach (array_keys($config['query_mapping']) as $var) {
				$data[$var] = get_query_var($var, '');
			}
		}

		// Use callback if provided
//		if (isset($config['callback']) && is_callable($config['callback'])) {
//			// Create a temporary template file for callback
//			return $this->createCallbackTemplate($config['callback'], $data);
//		}

		// Use template if provided
		$template = $config['template'] ?? null;
		if ($template) {
			$template_path = $this->findTemplate($template);
			if ($template_path) {
				// Store data    globally for template access
				$GLOBALS['wptoolkit_page_data'] = $data;
				return $template_path;
			}
		}

		// Create default template
		return $this->createDefaultFrontendTemplate($slug, $config, $data);
	}

	/**
	 * Find template file.
	 *
	 * @param string $template Template name
	 * @return string|false Template path or false
	 */
	protected function findTemplate(string $template): string|false
	{
		// Try plugin template directory
		$template_path = $this->template_dir . '/' . ltrim($template, '/');
		if (file_exists($template_path)) {
			return $template_path;
		}

		// Try theme template override
		$theme_template = locate_template([
			$this->app_slug . '-templates/' . basename($template),
			'plugin-templates/' . basename($template)
		]);

		return $theme_template ?: false;
	}

	/**
	 * Create callback template.
	 *
	 * @param callable $callback Callback function
	 * @param array $data Template data
	 * @return string Template path
	 */
	protected function createCallbackTemplate(callable $callback, array $data): string
	{
		$temp_file = wp_upload_dir()['basedir'] . '/' . $this->app_slug . '_temp_' . md5(serialize($callback)) . '.php';

		if (!file_exists($temp_file)) {
			$template_content = '<?php 
        $data = $GLOBALS["wptoolkit_page_data"] ?? [];
        extract($data);
        call_user_func($GLOBALS["wptoolkit_callback"], $data);
        ?>';

			file_put_contents($temp_file, $template_content);
		}

		$GLOBALS['wptoolkit_callback'] = $callback;
		return $temp_file;
	}

	/**
	 * Create default frontend template.
	 *
	 * @param string $slug Page slug
	 * @param array $config Page configuration
	 * @param array $data Template data
	 * @return string Template path
	 */
	protected function createDefaultFrontendTemplate(string $slug, array $config, array $data): string
	{
		$temp_file = wp_upload_dir()['basedir'] . '/' . $this->app_slug . '_default_' . $slug . '.php';

		$template_content = '<?php
		    get_header();
		    $data = $GLOBALS["wptoolkit_page_data"] ?? [];
		    ?>
		    <div class="plugin-page <?php echo esc_attr($data["app_slug"]); ?>-page <?php echo esc_attr($data["app_slug"]); ?>-page-<?php echo esc_attr($data["page_slug"]); ?>">
		        <div class="container">
		            <h1><?php echo esc_html($data["page_config"]["title"] ?? "Plugin Page"); ?></h1>
		            <p><?php _e("This page has no content configured.", $data["app_slug"]); ?></p>
		        </div>
		    </div>
		    <?php
		    get_footer();
		    ?>';

		if (!file_exists($temp_file)) {
			file_put_contents($temp_file, $template_content);
		}

		return $temp_file;
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
     * Enhanced current page detection with Model support.
     *
     * @return void
     */
    protected function detectCurrentPage(): void
    {
        if (is_admin()) {
            // Check for model-based pages first
            $post_type = sanitize_text_field($_GET['post_type'] ?? '');
            if ($post_type) {
                $model_slug = "edit.php?post_type={$post_type}";

                if (isset($this->menu_pages[$model_slug])) {
                    $this->current_page = [
                        'type' => 'admin',
                        'slug' => $model_slug,
                        'config' => $this->menu_pages[$model_slug],
                        'is_model_page' => true,
                        'post_type' => $post_type,
                    ];
                    return;
                } elseif (isset($this->submenu_pages[$model_slug])) {
                    $this->current_page = [
                        'type' => 'admin',
                        'slug' => $model_slug,
                        'config' => $this->submenu_pages[$model_slug],
                        'is_model_page' => true,
                        'post_type' => $post_type,
                    ];
                    return;
                }
            }

            // Fallback to regular page detection
            $page = sanitize_text_field($_GET['page'] ?? '');
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
            // Frontend page detection (unchanged)
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
	 *
	 * @return callable|false Page callback
	 */
    protected function getPageCallback(string $slug, array $config): callable|false
    {
        $callback = $config['callback'];

        if ($callback === false) return false;

        if ($callback && is_callable($callback)) {
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

		// Enqueue page assets
		$this->enqueuePageAssets($config);

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

	protected function renderFrontendPage(string $slug, array $config, array $data = []): void
	{
		// Enqueue page assets
		$this->enqueuePageAssets($config);

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
	 * Set the asset manager instance.
	 *
	 * @param EnqueueManager $asset_manager Asset manager instance
	 * @param array $default_groups Default asset groups configuration
	 * @return static For method chaining
	 */
	public function setAssetManager(
		EnqueueManager $asset_manager,
		array $default_groups = []
	): static {
		$this->asset_manager = $asset_manager;

		// Merge with existing default groups
		if (!empty($default_groups)) {
			$this->default_asset_groups = array_merge_recursive(
				$this->default_asset_groups,
				$default_groups
			);
		}

		return $this;
	}

// 3. ADD THESE HELPER METHODS

	/**
	 * Get the asset manager instance.
	 *
	 * @return EnqueueManager|null Asset manager instance or null
	 */
	public function getAssetManager(): ?EnqueueManager
	{
		return $this->asset_manager;
	}

	/**
	 * Check if asset manager is available.
	 *
	 * @return bool Whether asset manager is set
	 */
	public function hasAssetManager(): bool
	{
		return $this->asset_manager !== null;
	}

	/**
	 * Set default asset groups for page types.
	 *
	 * @param array $groups Asset groups configuration
	 * @return static For method chaining
	 */
	public function setDefaultAssetGroups(array $groups): static
	{
		$this->default_asset_groups = array_merge_recursive(
			$this->default_asset_groups,
			$groups
		);

		return $this;
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
