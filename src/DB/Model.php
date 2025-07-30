<?php

/**
 * Enhanced Abstract Model with Singleton Pattern and Admin Customization
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\DB;

use Codad5\WPToolkit\Utils\{Config, Cache, InputValidator};
use WP_Post;
use WP_Error;
use WP_Query;
use Exception;
use WP_Post_Type;

/**
 * Enhanced Abstract Model class for WordPress custom post types with comprehensive admin integration.
 * 
 * Features:
 * - Enforced singleton pattern for all child classes
 * - Custom admin columns with quick edit support
 * - Advanced authentication and access control
 * - MetaBox integration with easy value retrieval
 * - Comprehensive CRUD operations with caching
 * - REST API support and advanced querying
 */
abstract class Model
{
    /**
     * Post type identifier - must be defined in child classes.
     */
    protected const POST_TYPE = '';

    /**
     * Meta prefix for this model - should be defined in child classes.
     */
    protected const META_PREFIX = '';

    /**
     * Whether this post type requires authentication to view.
     */
    protected const REQUIRES_AUTHENTICATION = false;

    /**
     * Minimum capability required to view this post type.
     */
    protected const VIEW_CAPABILITY = 'read';

    /**
     * Whether the model is currently running/initialized.
     */
    protected bool $is_running = false;

    /**
     * Whether the model has been initialized at least once.
     */
    protected bool $has_been_initialized = false;

    /**
     * Store registered hook callbacks for removal during deactivation.
     */
    protected array $registered_hooks = [];

    /**
     * Registered MetaBox instances.
     *
     * @var MetaBox[]
     */
    protected array $metaBoxes = [];

    /**
     * Configuration instance for the model.
     */
    protected Config $config;

    /**
     * Cache group for this model.
     */
    protected string $cache_group;

    /**
     * Cache duration in seconds.
     */
    protected int $cache_duration = 3600; // 1 hour

    /**
     * Whether to enable caching for this model.
     */
    protected bool $enable_cache = true;

    /**
     * Validation errors from the last operation.
     */
    protected array $validation_errors = [];

    /**
     * Cached admin columns configuration.
     */
    private ?array $admin_columns_cache = null;

    /**
     * Constructor - protected to enforce singleton pattern.
     *
     * @param Config $config Configuration instance
     */
    protected function __construct(Config $config)
    {
        $this->config = $config;
        $this->cache_group = static::POST_TYPE . '_model';
    }

    /**
     * Singleton instances cache.
     *
     * @var Model[]
     */
    private static array $instances = [];

	/**
	 * Get the singleton instance of this model.
	 *
	 * @param Config|null $config Configuration instance
	 *
	 * @return static Singleton instance of the model
	 * @throws Exception
	 */
    public static function get_instance(?Config $config = null): static
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            if ($config === null) throw new Exception("Config instance is required for model instantiation: $class");
            self::$instances[$class] = new static($config);
        }

        return self::$instances[$class];
    }


    /**
     * Get the post type for this model.
     *
     * @return string Post type identifier
     */
    final public function get_post_type(): string
    {
        return static::POST_TYPE;
    }

    /**
     * Get the meta prefix for this model.
     *
     * @return string Meta prefix
     */
    final public function get_meta_prefix(): string
    {
        return static::META_PREFIX;
    }

    /**
     * Get post type arguments for registration.
     * 
     * Child classes should override this to customize post type settings.
     *
     * @return array Post type arguments
     */
    abstract protected static function get_post_type_args(): array;

    /**
     * Get taxonomies configuration for this model.
     * 
     * Child classes can override this to define custom taxonomies.
     * 
     * Return format:
     * [
     *     'taxonomy_slug' => [
     *         'labels' => [
     *             'name' => 'Plural Name',
     *             'singular_name' => 'Singular Name',
     *             // ... other labels
     *         ],
     *         'args' => [
     *             'hierarchical' => true|false,
     *             'public' => true|false,
     *             // ... other taxonomy args
     *         ]
     *     ],
     *     // OR simplified format:
     *     'simple_taxonomy' => 'Display Name', // Uses defaults with this display name
     * ]
     *
     * @return array Taxonomies configuration
     */
    protected function get_taxonomies(): array
    {
        return [];
    }




    /**
     * Define custom admin columns configuration.
     * 
     * Return false to use default WordPress columns, or return an array like:
     * [
     *     'column_key' => [
     *         'label' => 'Column Label',
     *         'type' => 'text|number|date|select|custom',
     *         'sortable' => true|false|callable,
     *         'allow_quick_edit' => true|false,
     *         'metabox_id' => 'metabox_id', // Required for quick edit
     *         'field_id' => 'field_id', // Required for quick edit
     *         'get_value' => callable|string, // Function to get the value
     *         'position' => 'after_title|after_date|end', // Column position
     *         'width' => '100px|10%', // Column width
     *     ]
     * ]
     *
     * @return array|false Admin columns configuration or false for defaults
     */
    protected function get_admin_columns(): array|false
    {
        return false;
    }

	private function show_post_row(array $actions, WP_Post $post):array {
		// if the post type is not the current post type, return the actions as is
		if ($post->post_type !== static::POST_TYPE) {
			return $this->get_post_row($actions, $post);
		}
		return $actions;
	}


	protected  function get_post_row(array $actions, WP_Post $post): array {
		return $actions;
	}

    /**
     * Register the custom post type.
     *
     * @return WP_Post_Type|WP_Error Post type object on success, WP_Error on failure
     */
    final public function register_post_type(): WP_Post_Type|WP_Error
    {
        if (empty(static::POST_TYPE)) {
            return new WP_Error('no_post_type', 'POST_TYPE constant must be defined in child class');
        }

        $args = array_merge([
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => static::POST_TYPE],
        ], static::get_post_type_args());

        return register_post_type(static::POST_TYPE, $args);
    }

    /**
     * Handle authentication and access control.
     *
     * @return void
     */
    public function handle_authentication(): void
    {
        if (!static::REQUIRES_AUTHENTICATION) {
            return;
        }

        if (is_post_type_archive(static::POST_TYPE) || is_singular(static::POST_TYPE)) {
            if (!is_user_logged_in()) {
                wp_redirect(wp_login_url(get_permalink()));
                exit;
            }

            if (!current_user_can(static::VIEW_CAPABILITY)) {
                wp_die(__('You do not have permission to view this content.', $this->config->get('textdomain', 'default')));
            }
        }
    }

    /**
     * Setup admin columns based on configuration.
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function setup_admin_columns(array $columns): array
    {
        $admin_columns = $this->get_admin_columns();

        if ($admin_columns === false) {
            return $columns;
        }

        $new_columns = [];

        // Keep checkbox and title
        if (isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        if (isset($columns['title'])) {
            $new_columns['title'] = $columns['title'];
        }

        // Add custom columns based on position
        foreach ($admin_columns as $key => $config) {
            $position = $config['position'] ?? 'end';
            $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $key));

            if ($position === 'after_title') {
                $new_columns[$key] = $label;
            }
        }

        // Add date column if it exists
        if (isset($columns['date'])) {
            $new_columns['date'] = $columns['date'];

            // Add after_date columns
            foreach ($admin_columns as $key => $config) {
                if (($config['position'] ?? 'end') === 'after_date') {
                    $new_columns[$key] = $config['label'] ?? ucfirst(str_replace('_', ' ', $key));
                }
            }
        }

        // Add end columns
        foreach ($admin_columns as $key => $config) {
            if (($config['position'] ?? 'end') === 'end') {
                $new_columns[$key] = $config['label'] ?? ucfirst(str_replace('_', ' ', $key));
            }
        }

        $this->admin_columns_cache = $admin_columns;
        return $new_columns;
    }

    /**
     * Render custom admin column content.
     *
     * @param string $column Column identifier
     * @param int $post_id Post ID
     * @return void
     */
    public function render_admin_column(string $column, int $post_id): void
    {
        $admin_columns = $this->admin_columns_cache ?? $this->get_admin_columns();

        if (!isset($admin_columns[$column])) {
            return;
        }

        $config = $admin_columns[$column];
        $value = $this->get_column_value($post_id, $column, $config);

        // Format value based on type
        switch ($config['type'] ?? 'text') {
            case 'date':
                if ($value) {
                    echo esc_html(date_i18n(get_option('date_format'), strtotime($value)));
                }
                break;

            case 'number':
                if (is_numeric($value)) {
                    echo esc_html(number_format_i18n((float)$value));
                }
                break;

            case 'currency':
                if (is_numeric($value)) {
                    $currency_symbol = $config['currency_symbol'] ?? '$';
                    echo esc_html($currency_symbol . number_format((float)$value, 2));
                }
                break;

            default:
                echo esc_html($value);
                break;
        }
    }

    /**
     * Setup sortable columns.
     *
     * @param array $columns Existing sortable columns
     * @return array Modified sortable columns
     */
    public function setup_sortable_columns(array $columns): array
    {
        $admin_columns = $this->get_admin_columns();

        if ($admin_columns === false) {
            return $columns;
        }

        foreach ($admin_columns as $key => $config) {
            if ($config['sortable'] ?? false) {
                if (is_callable($config['sortable'])) {
                    $columns[$key] = $key . '_custom';
                } else {
                    $columns[$key] = $this->get_meta_key_for_column($key, $config);
                }
            }
        }

        return $columns;
    }

    /**
     * Handle column sorting in admin.
     *
     * @param WP_Query $query Query object
     * @return void
     */
    public function handle_column_sorting(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== static::POST_TYPE) {
            return;
        }

        $orderby = $query->get('orderby');
        $admin_columns = $this->get_admin_columns();

        if (!$admin_columns || !isset($admin_columns[str_replace('_custom', '', $orderby)])) {
            return;
        }

        $column_key = str_replace('_custom', '', $orderby);
        $config = $admin_columns[$column_key];

        if (is_callable($config['sortable'] ?? false)) {
            // Custom sorting logic
            call_user_func($config['sortable'], $query, $column_key, $config);
        } else {
            // Standard meta sorting
            $meta_key = $this->get_meta_key_for_column($column_key, $config);
            $query->set('meta_key', $meta_key);

            $orderby_type = ($config['type'] ?? 'text') === 'number' ? 'meta_value_num' : 'meta_value';
            $query->set('orderby', $orderby_type);
        }
    }



    /**
     * Get column value for display.
     *
     * @param int $post_id Post ID
     * @param string $column_key Column key
     * @param array $config Column configuration
     * @return mixed Column value
     */
    protected function get_column_value(int $post_id, string $column_key, array $config): mixed
    {
        if (isset($config['get_value'])) {
            if (is_callable($config['get_value'])) {
                return call_user_func($config['get_value'], $post_id);
            }

            if (is_string($config['get_value']) && method_exists($this, $config['get_value'])) {
                return $this->{$config['get_value']}($post_id);
            }
        }

        // Try to get from MetaBox if metabox_id and field_id are specified
        if (isset($config['metabox_id']) && isset($config['field_id'])) {
            $metabox = $this->getMetabox($config['metabox_id']);
            if ($metabox) {
                return $metabox->get_field_value($config['field_id'], $post_id);
            }
        }

        // Default: try to get from meta
        $meta_key = $this->get_meta_key_for_column($column_key, $config);
        return get_post_meta($post_id, $meta_key, true);
    }

    /**
     * Get meta key for a column.
     *
     * @param string $column_key Column key
     * @param array $config Column configuration
     * @return string Meta key
     */
    protected function get_meta_key_for_column(string $column_key, array $config): string
    {
        if (isset($config['meta_key'])) {
            return $config['meta_key'];
        }

        // If metabox and field are specified, construct the meta key
        if (isset($config['metabox_id']) && isset($config['field_id'])) {
            $metabox = $this->getMetabox($config['metabox_id']);
            if ($metabox) {
                return $metabox->get_prefix() . $config['field_id'];
            }
        }

        return static::META_PREFIX . $column_key;
    }
    //=======================================END



    /**
     * Get a MetaBox by ID.
     *
     * @param string $metabox_id MetaBox identifier
     * @return MetaBox|null MetaBox instance or null if not found
     */
    public function getMetabox(string $metabox_id): ?MetaBox
    {
        foreach ($this->metaBoxes as $metabox) {
            if ($metabox->id === $metabox_id) {
                return $metabox;
            }
        }
        return null;
    }

    /**
     * Get a meta value from a specific MetaBox field.
     *
     * @param int $post_id Post ID
     * @param string $metabox_id MetaBox identifier
     * @param string $field_id Field identifier
     * @return mixed Field value
     */
    public function getMetaboxFieldValue(int $post_id, string $metabox_id, string $field_id): mixed
    {
        $metabox = $this->getMetabox($metabox_id);

        if (!$metabox) {
            return null;
        }

        return $metabox->get_field_value($field_id, $post_id);
    }

    /**
     * Update a meta value through a specific MetaBox field.
     *
     * @param int $post_id Post ID
     * @param string $metabox_id MetaBox identifier
     * @param string $field_id Field identifier
     * @param mixed $value New value
     * @return bool|WP_Error Success status
     */
    public function updateMetaboxFieldValue(int $post_id, string $metabox_id, string $field_id, mixed $value): bool|WP_Error
    {
        $metabox = $this->getMetabox($metabox_id);

        if (!$metabox) {
            return new WP_Error('metabox_not_found', 'MetaBox not found');
        }

        return $metabox->save_field($post_id, $field_id, $value);
    }

    /**
     * Register a MetaBox with this model.
     *
     * @param MetaBox $metabox MetaBox instance to register
     * @return self
     */
    public function register_metabox(MetaBox $metabox): self
    {
        if (!in_array($metabox, $this->metaBoxes, true)) {
            $metabox->setup_actions();
            $this->metaBoxes[] = $metabox;
        }
        return $this;
    }

    /**
     * Register multiple MetaBoxes at once.
     *
     * @param MetaBox[] $metaboxes Array of MetaBox instances
     * @return self
     */
    public function register_metaboxes(array $metaboxes): self
    {
        foreach ($metaboxes as $metabox) {
            $this->register_metabox($metabox);
        }
        return $this;
    }

    /**
     * Get all registered MetaBoxes.
     *
     * @return MetaBox[] Array of MetaBox instances
     */
    public function get_metaboxes(): array
    {
        return $this->metaBoxes;
    }

    /**
     * Get expected fields from all registered MetaBoxes.
     *
     * @return array Expected fields configuration
     */
    public function get_expected_fields(): array
    {
        $expected_fields = [];

        foreach ($this->metaBoxes as $metaBox) {
            foreach ($metaBox->get_fields() as $field) {
                $expected_fields[$field['id']] = [
                    'required' => $field['attributes']['required'] ?? $field['required'] ?? false,
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'options' => $field['options'] ?? [],
                    'metabox_id' => $metaBox->id
                ];
            }
        }

        return $expected_fields;
    }

    /**
     * Create a new post with metadata.
     *
     * @param array $post_data Post data (title, content, etc.)
     * @param array $meta_data Metadata key-value pairs
     * @param bool $validate Whether to validate fields
     * @return int|WP_Error Post ID on success, WP_Error on failure
     */
    public function create(array $post_data, array $meta_data = [], bool $validate = true): int|WP_Error
    {
        // Validate fields if requested
        if ($validate && !$this->validate_fields($meta_data)) {
            return new WP_Error('validation_failed', 'Field validation failed', $this->validation_errors);
        }

        // Set post type
        $post_data['post_type'] = static::POST_TYPE;

        // Set default status if not provided
        if (!isset($post_data['post_status'])) {
            $post_data['post_status'] = 'publish';
        }

        // Create the post
        $post_id = wp_insert_post($post_data, true);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty($meta_data)) {
            $this->save_meta_through_metaboxes($post_id, $meta_data);
        }

        // Clear cache
        if ($this->enable_cache) {
            $this->clear_post_cache($post_id);
        }

        return $post_id;
    }

    /**
     * Update an existing post with metadata.
     *
     * @param int $post_id Post ID
     * @param array $post_data Post data to update
     * @param array $meta_data Metadata to update
     * @param bool $validate Whether to validate fields
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function update(int $post_id, array $post_data = [], array $meta_data = [], bool $validate = true): bool|WP_Error
    {
        // Verify post exists and is correct type
        $post = get_post($post_id);
        if (!$post || $post->post_type !== static::POST_TYPE) {
            return new WP_Error('invalid_post', 'Post not found or invalid type');
        }

        // Validate fields if requested
        if ($validate && !empty($meta_data) && !$this->validate_fields($meta_data)) {
            return new WP_Error('validation_failed', 'Field validation failed', $this->validation_errors);
        }

        // Update post data if provided
        if (!empty($post_data)) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);

            if (is_wp_error($result)) {
                return $result;
            }
        }

        // Update metadata through MetaBoxes
        if (!empty($meta_data)) {
            $this->save_meta_through_metaboxes($post_id, $meta_data);
        }

        // Clear cache
        if ($this->enable_cache) {
            $this->clear_post_cache($post_id);
        }

        return true;
    }

    /**
     * Get a post by ID with metadata and taxonomies.
     *
     * @param int $post_id Post ID
     * @param bool $strip_meta_key Whether to strip the meta key prefix
     * @param array $config Additional configuration options
     * * - include_meta: Whether to include metadata (default true)
     * * - include_taxonomies: Whether to include taxonomy terms (default false)
     * * - full_taxonomies_terms: Whether to return full term objects instead of names (default false)
     * @return array|null Post data array or null if not found
     */
    public function get_post(int $post_id, ?bool $strip_meta_key = true, array $config = []): ?array
    {
        $include_meta = $config['include_meta'] ?? true;
        $include_taxonomies = $config['include_taxonomies'] ?? false;
        $full_taxonomies_terms = $config['full_taxonomies_terms'] ?? false;

        // Generate cache key based on configuration
        $cache_suffix = $this->generate_cache_suffix($include_meta, $include_taxonomies, $full_taxonomies_terms);

        // Check cache first
        if ($this->enable_cache) {
            $cache_key = "post_{$post_id}_{$cache_suffix}";
            $cached = Cache::get($cache_key, null, $this->cache_group);

            if ($cached !== null) {
                return $cached;
            }
        }

        $post = get_post($post_id);

        if (!$post || $post->post_type !== static::POST_TYPE) {
            return null;
        }

        // Build post data using the reusable method
        $result = $this->build_post_data($post, $strip_meta_key, $include_meta, $include_taxonomies, $full_taxonomies_terms);

        // Cache the result
        if ($this->enable_cache) {
            $cache_key = "post_{$post_id}_{$cache_suffix}";
            Cache::set($cache_key, $result, $this->cache_duration, $this->cache_group);
        }

        return $result;
    }

    /**
     * Get posts with optional filtering, metadata, and taxonomies.
     *
     * @param array $args Query arguments
     * @param bool $strip_meta_key Whether to strip the meta key prefix
     * @param array $config Additional configuration options 
     * * - include_meta: Whether to include metadata (default false)
     * * - include_taxonomies: Whether to include taxonomy terms (default false)
     * * - full_taxonomies_terms: Whether to return full term objects instead of names (default false)
     * @return array Array of post data
     */
    public function get_posts(array $args = [], ?bool $strip_meta_key = true, array $config = []): array
    {
        // Set post type in query args
        $args['post_type'] = static::POST_TYPE;
        $include_meta = $config['include_meta'] ?? true;
        $include_taxonomies = $config['include_taxonomies'] ?? false;
        $full_taxonomies_terms = $config['full_taxonomies_terms'] ?? false;

        // Set default number of posts
        if (!isset($args['posts_per_page'])) {
            $args['posts_per_page'] = 10;
        }

        // Generate cache key based on configuration
        $cache_suffix = $this->generate_cache_suffix($include_meta, $include_taxonomies, $full_taxonomies_terms);

        // Check cache for simple queries
        $cache_key = null;
        if ($this->enable_cache && count($args) <= 3) { // Simple query
            $cache_key = 'posts_' . md5(serialize($args)) . "_{$cache_suffix}";
            $cached = Cache::get($cache_key, null, $this->cache_group);

            if ($cached !== null) {
                return $cached;
            }
        }

        $posts = get_posts($args);
        $results = [];

        foreach ($posts as $post) {
            $results[] = $this->build_post_data($post, $strip_meta_key, $include_meta, $include_taxonomies, $full_taxonomies_terms);
        }

        // Cache simple queries
        if ($cache_key) {
            Cache::set($cache_key, $results, $this->cache_duration, $this->cache_group);
        }

        return $results;
    }

    /**
     * Build post data structure with optional metadata and taxonomies.
     * This is the reusable core method used by both get_post and get_posts.
     *
     * @param WP_Post $post Post object
     * @param bool|null $strip_meta_key Whether to strip the meta key prefix
     * @param bool $include_meta Whether to include metadata
     * @param bool $include_taxonomies Whether to include taxonomy terms
     * @param bool $full_taxonomies_terms Whether to return full term objects
     * @return array Post data structure
     */
    protected function build_post_data(
        WP_Post $post,
        ?bool $strip_meta_key,
        bool $include_meta,
        bool $include_taxonomies,
        bool $full_taxonomies_terms
    ): array {
        $post_data = ['post' => $post];

        if ($include_meta) {
            $post_data['meta'] = $this->get_post_meta($post->ID, $strip_meta_key);
        }

        if ($include_taxonomies) {
            $post_data['taxonomies'] = $this->build_taxonomies_data($post->ID, $full_taxonomies_terms);
        }

        return $post_data;
    }

    /**
     * Build taxonomies data for a post.
     *
     * @param int $post_id Post ID
     * @param bool $full_terms Whether to return full term objects or just names
     * @return array Taxonomies data
     */
    protected function build_taxonomies_data(int $post_id, bool $full_terms = false): array
    {
        $taxonomies_data = [];

        foreach ($this->get_registered_taxonomies() as $taxonomy) {
            $terms = $this->get_post_terms($post_id, $taxonomy->name);

            if ($terms && !is_wp_error($terms)) {
                if ($full_terms) {
                    $taxonomies_data[$taxonomy->name] = $terms;
                } else {
                    $taxonomies_data[$taxonomy->name] = array_map(fn($term) => $term->name, $terms);
                }
            } else {
                $taxonomies_data[$taxonomy->name] = [];
            }
        }

        return $taxonomies_data;
    }

    /**
     * Generate cache suffix based on configuration options.
     *
     * @param bool $include_meta Whether metadata is included
     * @param bool $include_taxonomies Whether taxonomies are included
     * @param bool $full_taxonomies_terms Whether full taxonomy terms are included
     * @return string Cache suffix
     */
    protected function generate_cache_suffix(bool $include_meta, bool $include_taxonomies, bool $full_taxonomies_terms): string
    {
        $parts = [];

        $parts[] = $include_meta ? 'with_meta' : 'no_meta';
        $parts[] = $include_taxonomies ? 'with_taxonomies' : 'no_taxonomies';

        if ($include_taxonomies) {
            $parts[] = $full_taxonomies_terms ? 'full_terms' : 'term_names';
        }

        return implode('_', $parts);
    }



    /**
     * Convenience method to get post with all data (metadata and taxonomies).
     *
     * @param int $post_id Post ID
     * @param bool $strip_meta_key Whether to strip the meta key prefix
     * @param bool $full_taxonomies_terms Whether to return full term objects
     * @return array|null Complete post data or null if not found
     */
    public function get_full_post(int $post_id, ?bool $strip_meta_key = null, bool $full_taxonomies_terms = false): ?array
    {
        return $this->get_post($post_id, $strip_meta_key, [
            'include_meta' => true,
            'include_taxonomies' => true,
            'full_taxonomies_terms' => $full_taxonomies_terms
        ]);
    }

    /**
     * Convenience method to get posts with all data (metadata and taxonomies).
     *
     * @param array $args Query arguments
     * @param bool $strip_meta_key Whether to strip the meta key prefix
     * @param bool $full_taxonomies_terms Whether to return full term objects
     * @return array Array of complete post data
     */
    public function get_full_posts(array $args = [], ?bool $strip_meta_key = null, bool $full_taxonomies_terms = false): array
    {
        return $this->get_posts($args, $strip_meta_key, [
            'include_meta' => true,
            'include_taxonomies' => true,
            'full_taxonomies_terms' => $full_taxonomies_terms
        ]);
    }
    /**
     * Delete a post and its metadata.
     *
     * @param int $post_id Post ID
     * @param bool $force_delete Whether to bypass trash
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function delete(int $post_id, bool $force_delete = false): bool|WP_Error
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== static::POST_TYPE) {
            return new WP_Error('invalid_post', 'Post not found or invalid type');
        }

        $result = wp_delete_post($post_id, $force_delete);

        if (!$result) {
            return new WP_Error('delete_failed', 'Failed to delete post');
        }

        // Clear cache
        if ($this->enable_cache) {
            $this->clear_post_cache($post_id);
        }

        return true;
    }

    /**
     * Enhanced search posts by title, content, metadata, and taxonomies.
     *
     * @param string $search_term Search term
     * @param array $search_fields Fields to search in ('title', 'content', 'meta', 'taxonomies')
     * @param array $args Additional query arguments
     * @param array $config Search configuration options
     * * - include_meta: Whether to include metadata in results (default true)
     * * - include_taxonomies: Whether to include taxonomy terms in results (default true)
     * * - full_taxonomies_terms: Whether to return full term objects (default false)
     * * - strip_meta_key: Whether to strip meta key prefix (default null)
     * @return array Search results with relevance scores
     */
    public function search(
        string $search_term,
        array $search_fields = ['title', 'content'],
        array $args = [],
        array $config = []
    ): array {
        $include_meta = $config['include_meta'] ?? true;
        $include_taxonomies = $config['include_taxonomies'] ?? true;
        $full_taxonomies_terms = $config['full_taxonomies_terms'] ?? false;
        $strip_meta_key = $config['strip_meta_key'] ?? null;

        $args = array_merge($args, [
            'post_type' => static::POST_TYPE,
            'posts_per_page' => $args['posts_per_page'] ?? 20,
        ]);

        // Handle meta search
        if (in_array('meta', $search_fields)) {
            $args['meta_query'] = [
                'relation' => 'OR'
            ];

            foreach ($this->get_expected_fields() as $field_id => $field_config) {
                $args['meta_query'][] = [
                    'key' => $field_id,
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ];
            }
        }

        // Handle taxonomy search
        if (in_array('taxonomies', $search_fields)) {
            $tax_queries = [];
            $registered_taxonomies = array_keys($this->get_registered_taxonomies());

            foreach ($registered_taxonomies as $taxonomy) {
                $tax_queries[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'name',
                    'terms' => $search_term,
                    'operator' => 'LIKE'
                ];
            }

            if (!empty($tax_queries)) {
                if (isset($args['meta_query'])) {
                    // Combine meta and tax queries with OR relation at the top level
                    $args['_meta_query'] = $args['meta_query']; // Backup
                    unset($args['meta_query']);

                    $args['meta_query'] = [
                        'relation' => 'OR',
                        $args['_meta_query'],
                        [
                            'relation' => 'OR',
                            'tax_query' => $tax_queries
                        ]
                    ];
                } else {
                    $args['tax_query'] = [
                        'relation' => 'OR',
                        ...$tax_queries
                    ];
                }
            }
        }

        // Handle title/content search
        if (array_intersect(['title', 'content'], $search_fields)) {
            $args['s'] = $search_term;
        }

        $query = new WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $post_data = $this->build_post_data(
                $post,
                $strip_meta_key,
                $include_meta,
                $include_taxonomies,
                $full_taxonomies_terms
            );

            $post_data['relevance'] = $this->calculate_relevance($post, $search_term, $search_fields);
            $results[] = $post_data;
        }

        // Sort by relevance
        usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return $results;
    }

    /**
     * Search posts with autocomplete suggestions.
     *
     * @param string $search_term Partial search term
     * @param int $limit Maximum number of suggestions
     * @param array $search_fields Fields to search in
     * @return array Autocomplete suggestions
     */
    public function search_autocomplete(string $search_term, int $limit = 10, array $search_fields = ['title']): array
    {
        if (strlen($search_term) < 2) {
            return [];
        }

        $args = [
            'post_type' => static::POST_TYPE,
            'posts_per_page' => $limit * 2, // Get more to filter
            'post_status' => 'publish',
        ];

        // For autocomplete, we primarily search titles
        if (in_array('title', $search_fields)) {
            $args['s'] = $search_term;
        }

        $posts = get_posts($args);
        $suggestions = [];

        foreach ($posts as $post) {
            $suggestion = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => get_permalink($post->ID),
                'relevance' => $this->calculate_relevance($post, $search_term, $search_fields)
            ];

            // Add taxonomy terms for richer suggestions
            if (in_array('taxonomies', $search_fields)) {
                $taxonomies = $this->build_taxonomies_data($post->ID, false);
                $suggestion['terms'] = [];

                foreach ($taxonomies as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        $suggestion['terms'][$taxonomy] = array_slice($terms, 0, 3); // Limit terms
                    }
                }
            }

            $suggestions[] = $suggestion;
        }

        // Sort by relevance and limit
        usort($suggestions, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Save post using registered MetaBoxes.
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     * @return bool Success status
     */
    public function save_post(int $post_id, WP_Post $post): bool
    {
        // Skip for wrong post type
        if ($post->post_type !== static::POST_TYPE) {
            return false;
        }

        // Skip for autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        $success = true;

        try {
            foreach ($this->metaBoxes as $metaBox) {
                $success = $success && $metaBox->save($post_id);
            }

            // Clear cache on successful save
            if ($success && $this->enable_cache) {
                $this->clear_post_cache($post_id);
            }
        } catch (Exception $e) {
            error_log('Model save error: ' . $e->getMessage());
            return false;
        }

        return $success;
    }

    /**
     * Handle post deletion to clear cache.
     *
     * @param int $post_id Post ID
     * @return void
     */
    public function handle_post_deletion(int $post_id): void
    {
        $post = get_post($post_id);

        if ($post && $post->post_type === static::POST_TYPE && $this->enable_cache) {
            $this->clear_post_cache($post_id);
        }
    }

    /**
     * Get validation errors from the last operation.
     *
     * @return array Validation errors
     */
    public function get_validation_errors(): array
    {
        return $this->validation_errors;
    }

    /**
     * Enable or disable caching for this model.
     *
     * @param bool $enable Whether to enable caching
     * @return self
     */
    public function set_caching(bool $enable): self
    {
        $this->enable_cache = $enable;
        return $this;
    }

    /**
     * Set cache duration for this model.
     *
     * @param int $duration Cache duration in seconds
     * @return self
     */
    public function set_cache_duration(int $duration): self
    {
        $this->cache_duration = $duration;
        return $this;
    }

    /**
     * Get post metadata organized by MetaBox.
     *
     * @param int $post_id Post ID
     * @param bool $strip Whether to strip the key prefix
     * @return array Organized metadata
     */
    protected function get_post_meta(int $post_id, ?bool $strip = null): array
    {
        $meta = [];

        foreach ($this->metaBoxes as $metaBox) {
            $metabox_data = $metaBox->all_meta($post_id, $strip);
            $metabox_id = property_exists($metaBox, 'id') ? $metaBox->id : 'unknown';
            $meta[$metabox_id] = $metabox_data;
        }

        return $meta;
    }

    /**
     * Validate fields using registered MetaBoxes.
     *
     * @param array $data Data to validate
     * @return bool Validation result
     */
    protected function validate_fields(array $data): bool
    {
        return empty($this->get_error_fields($data));
    }

	/**
	 * Get error fields from the provided data.
	 * @param array $data
	 *
	 * @return array Array of validation errors with field IDs as keys
	 */

     public function get_error_fields(array $data): array
    {
        $this->validation_errors = [];
        $expected_fields = $this->get_expected_fields();

        foreach ($expected_fields as $field_id => $field_config) {
            $value = $data[$field_id] ?? null;

            // Check required fields
            if ($field_config['required'] && (empty($value) && $value !== '0')) {
                $this->validation_errors[$field_id] = $field_config['label'] . ' is required';
                continue;
            }

            // Skip validation for empty optional fields
            if (empty($value) && !$field_config['required']) {
                continue;
            }

            // Validate field type
            if (!InputValidator::validate($field_config['type'], $value, $field_config)) {
                $this->validation_errors[$field_id] = $field_config['label'] . ' is invalid';
            }
        }

        return $this->validation_errors;
    }

    /**
     * Enhanced calculate search relevance score with taxonomy support.
     *
     * @param WP_Post $post Post object
     * @param string $search_term Search term
     * @param array $search_fields Fields searched
     * @return float Relevance score
     */
    protected function calculate_relevance(WP_Post $post, string $search_term, array $search_fields): float
    {
        $score = 0;
        $term_lower = strtolower($search_term);
        $term_words = array_filter(explode(' ', $term_lower)); // Split multi-word searches

        // Title relevance (highest weight)
        if (in_array('title', $search_fields)) {
            $title_lower = strtolower($post->post_title);

            // Exact match gets highest score
            if ($title_lower === $term_lower) {
                $score += 50;
            }
            // Title starts with search term
            elseif (str_starts_with($title_lower, $term_lower)) {
                $score += 30;
            }
            // Contains full search term
            elseif (str_contains($title_lower, $term_lower)) {
                $score += 20;
            }

            // Individual word matches in title
            foreach ($term_words as $word) {
                if (str_contains($title_lower, $word)) {
                    $score += 5;
                }
            }
        }

        // Content relevance
        if (in_array('content', $search_fields)) {
            $content_lower = strtolower($post->post_content);

            // Full term matches
            $full_matches = substr_count($content_lower, $term_lower);
            $score += $full_matches * 3;

            // Individual word matches
            foreach ($term_words as $word) {
                $word_matches = substr_count($content_lower, $word);
                $score += $word_matches * 1;
            }
        }

        // Meta relevance
        if (in_array('meta', $search_fields)) {
            $meta = get_post_meta($post->ID);
            foreach ($meta as $key => $values) {
                // Skip WordPress internal meta
                if (str_starts_with($key, '_') && !str_starts_with($key, static::META_PREFIX)) {
                    continue;
                }

                foreach ($values as $value) {
                    $value_lower = strtolower($value);

                    // Exact match in meta
                    if ($value_lower === $term_lower) {
                        $score += 10;
                    }
                    // Contains full term
                    elseif (str_contains($value_lower, $term_lower)) {
                        $score += 3;
                    }

                    // Individual word matches
                    foreach ($term_words as $word) {
                        if (str_contains($value_lower, $word)) {
                            $score += 1;
                        }
                    }
                }
            }
        }

        // Taxonomy relevance
        if (in_array('taxonomies', $search_fields)) {
            $taxonomies = $this->build_taxonomies_data($post->ID, true); // Get full terms

            foreach ($taxonomies as $taxonomy_terms) {
                foreach ($taxonomy_terms as $term) {
                    if (!is_object($term)) continue;

                    $term_name_lower = strtolower($term->name);
                    $term_desc_lower = strtolower($term->description ?? '');

                    // Exact match in taxonomy term name
                    if ($term_name_lower === $term_lower) {
                        $score += 15;
                    }
                    // Contains full term in name
                    elseif (str_contains($term_name_lower, $term_lower)) {
                        $score += 8;
                    }

                    // Check description if available
                    if (!empty($term_desc_lower) && str_contains($term_desc_lower, $term_lower)) {
                        $score += 3;
                    }

                    // Individual word matches in taxonomy
                    foreach ($term_words as $word) {
                        if (str_contains($term_name_lower, $word)) {
                            $score += 2;
                        }
                        if (!empty($term_desc_lower) && str_contains($term_desc_lower, $word)) {
                            $score += 1;
                        }
                    }
                }
            }
        }

        // Boost score for newer content (time decay factor)
        $post_age_days = (time() - strtotime($post->post_date)) / (24 * 60 * 60);
        $freshness_boost = max(0, 5 - ($post_age_days / 30)); // Boost decreases over 150 days
        $score += $freshness_boost;

        return $score;
    }


    /**
     * Clear cache for a specific post.
     *
     * @param int $post_id Post ID
     * @return void
     */
    protected function clear_post_cache(int $post_id): void
    {
        // Clear specific post caches - all possible variations
        Cache::delete("post_{$post_id}_with_meta_no_taxonomies", $this->cache_group);
        Cache::delete("post_{$post_id}_no_meta_no_taxonomies", $this->cache_group);
        Cache::delete("post_{$post_id}_with_meta_with_taxonomies_term_names", $this->cache_group);
        Cache::delete("post_{$post_id}_no_meta_with_taxonomies_term_names", $this->cache_group);
        Cache::delete("post_{$post_id}_with_meta_with_taxonomies_full_terms", $this->cache_group);
        Cache::delete("post_{$post_id}_no_meta_with_taxonomies_full_terms", $this->cache_group);

        // Clear general posts cache (this is aggressive but ensures consistency)
        $stats = Cache::get_stats($this->cache_group);
        if (isset($stats['keys'])) {
            foreach ($stats['keys'] as $key) {
                if (str_contains($key, 'posts_')) {
                    Cache::delete($key, $this->cache_group);
                }
            }
        }
    }

    /**
     * Run setup before the model is fully initialized.
     * 
     * This method can be overridden by child classes to perform custom setup.
     * By default, it does nothing, making it optional for child classes.
     *
     * @return void
     */
    protected function before_run(): void
    {
        // Default implementation - can be overridden by child classes
        // Perform any pre-initialization setup here
    }

    /**
     * Run setup after the main initialization.
     * 
     * This method can be overridden by child classes to perform post-setup tasks.
     * By default, it does nothing.
     *
     * @return void
     */
    protected function after_run(): void
    {
        // Default implementation - can be overridden by child classes
        // Perform any post-initialization setup here
    }

    /**
     * Initialize and run the model with hooks and actions.
     * 
     * This method ensures the model is only initialized once and prevents double-calling.
     *
     * @param bool $force_reinitialize Force reinitialization even if already running
     * @return Model Returns self for method chaining
     */
    public function run(bool $force_reinitialize = false): Model
    {
        // Prevent double initialization unless forced
        if ($this->is_running && !$force_reinitialize) {
            return $this;
        }

        // If forcing reinitialization, deactivate first
        if ($force_reinitialize && $this->is_running) {
            $this->deactivate();
        }

        // Run pre-initialization setup
        $this->before_run();

        // Set up hooks and register functionality
        $this->setup_hooks();

        // Mark as running and initialized
        $this->is_running = true;
        $this->has_been_initialized = true;

        // Run post-initialization setup
        $this->after_run();

        return $this;
    }

    /**
     * Deactivate/pause the model by removing all registered hooks.
     * 
     * This allows the model to be temporarily disabled without losing instance data.
     *
     * @return Model Returns self for method chaining
     */
    public function deactivate(): Model
    {
        if (!$this->is_running) {
            return $this;
        }

        // Remove all registered hooks
        $this->remove_hooks();

        // Clear the registered hooks array
        $this->registered_hooks = [];

        // Mark as not running
        $this->is_running = false;

        return $this;
    }

    /**
     * Reactivate the model after deactivation.
     * 
     * This is essentially an alias for run() that makes the intent clearer.
     *
     * @return Model Returns self for method chaining
     */
    public function reactivate(): Model
    {
        return $this->run();
    }

    /**
     * Pause the model temporarily.
     * 
     * Alias for deactivate() to make intent clearer when temporarily stopping.
     *
     * @return Model Returns self for method chaining
     */
    public function pause(): Model
    {
        return $this->deactivate();
    }

    /**
     * Resume the model after pausing.
     * 
     * Alias for reactivate() to make intent clearer when resuming.
     *
     * @return Model Returns self for method chaining
     */
    public function resume(): Model
    {
        return $this->reactivate();
    }

    /**
     * Check if the model is currently running/active.
     *
     * @return bool True if the model is running, false otherwise
     */
    public function is_running(): bool
    {
        return $this->is_running;
    }

    /**
     * Check if the model has been initialized at least once.
     *
     * @return bool True if the model has been initialized, false otherwise
     */
    public function has_been_initialized(): bool
    {
        return $this->has_been_initialized;
    }

    /**
     * Get the current state of the model.
     *
     * @return array Model state information
     */
    public function get_state(): array
    {
        return [
            'is_running' => $this->is_running,
            'has_been_initialized' => $this->has_been_initialized,
            'post_type' => static::POST_TYPE,
            'registered_hooks_count' => count($this->registered_hooks),
            'metaboxes_count' => count($this->metaBoxes),
            'cache_enabled' => $this->enable_cache,
        ];
    }

    /**
     * Save metadata through registered MetaBoxes.
     *
     * @param int $post_id Post ID
     * @param array $meta_data Metadata to save
     * @return void
     */
    protected function save_meta_through_metaboxes(int $post_id, array $meta_data): void
    {
        foreach ($this->metaBoxes as $metabox) {
            foreach ($metabox->get_fields() as $field) {
                $field_id = $field['id'];
                if (array_key_exists($field_id, $meta_data)) {
                    $metabox->save_field($post_id, $field_id, $meta_data[$field_id]);
                }
            }
        }
    }

    /**
     * Enhanced setup_hooks method that includes taxonomy registration and autocomplete AJAX.
     *
     * @return void
     */
    protected function setup_hooks(): void
    {
        // Store hook information for later removal
        $this->add_tracked_action('init', [$this, 'register_post_type']);
        $this->add_tracked_action('init', [$this, 'register_taxonomies'], 11); // After post type registration
        $this->add_tracked_action('save_post_' . static::POST_TYPE, [$this, 'save_post'], 10, 2);
        $this->add_tracked_action('delete_post', [$this, 'handle_post_deletion']);

        // Admin columns hooks
        $this->add_tracked_filter('manage_' . static::POST_TYPE . '_posts_columns', [$this, 'setup_admin_columns']);
        $this->add_tracked_action('manage_' . static::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_column'], 10, 2);
        $this->add_tracked_filter('manage_edit-' . static::POST_TYPE . '_sortable_columns', [$this, 'setup_sortable_columns']);
        $this->add_tracked_action('pre_get_posts', [$this, 'handle_column_sorting']);

		//post row actions
        $this->add_tracked_filter('post_row_actions', [$this, 'show_post_row'], 10, 2);
        // Authentication hooks
        if (static::REQUIRES_AUTHENTICATION) {
            $this->add_tracked_action('template_redirect', [$this, 'handle_authentication']);
        }

        // AJAX search hooks
        $this->add_tracked_action('wp_ajax_' . static::POST_TYPE . '_search', [$this, 'handle_ajax_search']);
        $this->add_tracked_action('wp_ajax_nopriv_' . static::POST_TYPE . '_search', [$this, 'handle_ajax_search']);

        // AJAX autocomplete hooks
        $this->add_tracked_action('wp_ajax_' . static::POST_TYPE . '_autocomplete', [$this, 'handle_ajax_autocomplete']);
        $this->add_tracked_action('wp_ajax_nopriv_' . static::POST_TYPE . '_autocomplete', [$this, 'handle_ajax_autocomplete']);
    }

    /**
     * Enhanced handle AJAX search requests with better configuration support.
     *
     * @return void
     */
    public function handle_ajax_search(): void
    {
        check_ajax_referer(static::POST_TYPE . '_search', 'nonce');

        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $search_fields = array_map('sanitize_text_field', $_POST['fields'] ?? ['title', 'content']);
        $posts_per_page = (int) ($_POST['limit'] ?? 10);

        // Configuration options from AJAX request
        $config = [
            'include_meta' => filter_var($_POST['include_meta'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'include_taxonomies' => filter_var($_POST['include_taxonomies'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'full_taxonomies_terms' => filter_var($_POST['full_taxonomies_terms'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if (empty($search_term)) {
            wp_send_json_error('Search term is required');
        }

        try {
            $results = $this->search($search_term, $search_fields, [
                'posts_per_page' => $posts_per_page
            ], $config);

            wp_send_json_success([
                'results' => $results,
                'total' => count($results),
                'search_term' => $search_term,
                'fields_searched' => $search_fields
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Search failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX autocomplete requests.
     *
     * @return void
     */
    public function handle_ajax_autocomplete(): void
    {
        check_ajax_referer(static::POST_TYPE . '_autocomplete', 'nonce');

        $search_term = sanitize_text_field($_POST['term'] ?? '');
        $limit = (int) ($_POST['limit'] ?? 10);
        $search_fields = array_map('sanitize_text_field', $_POST['fields'] ?? ['title']);

        if (strlen($search_term) < 2) {
            wp_send_json_error('Search term must be at least 2 characters');
        }

        if ($limit > 50) {
            $limit = 50; // Cap the limit for performance
        }

        try {
            $suggestions = $this->search_autocomplete($search_term, $limit, $search_fields);

            wp_send_json_success([
                'suggestions' => $suggestions,
                'total' => count($suggestions),
                'search_term' => $search_term
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Autocomplete failed: ' . $e->getMessage());
        }
    }

    /**
     * Enqueue AJAX scripts and localize data for frontend search functionality.
     * Call this method in your theme or plugin to enable frontend AJAX search.
     *
     * @param bool $enqueue_autocomplete Whether to include autocomplete functionality
     * @return void
     */
    public function enqueue_search_scripts(bool $enqueue_autocomplete = true): void
    {
        // Only enqueue on frontend or admin pages where needed
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $script_data = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'post_type' => static::POST_TYPE,
            'search_nonce' => wp_create_nonce(static::POST_TYPE . '_search'),
            'autocomplete_nonce' => wp_create_nonce(static::POST_TYPE . '_autocomplete'),
            'strings' => [
                'searching' => __('Searching...', $this->config->get('textdomain', 'default')),
                'no_results' => __('No results found.', $this->config->get('textdomain', 'default')),
                'error' => __('Search error occurred.', $this->config->get('textdomain', 'default')),
            ]
        ];

        // Localize the script data
        wp_localize_script('jquery', static::POST_TYPE . '_search_data', $script_data);
    }


    /**
     * Register taxonomies defined in get_taxonomies().
     *
     * @return array Array of registered taxonomy names or WP_Error objects
     */
    final public function register_taxonomies(): array
    {
        $taxonomies = $this->get_taxonomies();
        $results = [];

        if (empty($taxonomies)) {
            return $results;
        }

        foreach ($taxonomies as $taxonomy_slug => $config) {
            $result = $this->register_single_taxonomy($taxonomy_slug, $config);
            $results[$taxonomy_slug] = $result;
        }

        return $results;
    }


    /**
     * Register a single taxonomy.
     *
     * @param string $taxonomy_slug Taxonomy slug/identifier
     * @param array|string $config Taxonomy configuration or simple display name
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    protected function register_single_taxonomy(string $taxonomy_slug, array|string $config): bool|WP_Error
    {
        // Handle simplified format (just a string for display name)
        if (is_string($config)) {
            $config = [
                'labels' => [
                    'name' => $config,
                    'singular_name' => rtrim($config, 's'), // Simple singularization
                ],
                'args' => []
            ];
        }

        // Validate configuration
        if (!isset($config['labels']['name'])) {
            return new WP_Error('missing_taxonomy_name', "Taxonomy name is required for: $taxonomy_slug");
        }

        // Generate default labels based on the name
        $name = $config['labels']['name'];
        $singular_name = $config['labels']['singular_name'] ?? rtrim($name, 's');

        $default_labels = [
            'name' => $name,
            'singular_name' => $singular_name,
            'add_new_item' => "Add New $singular_name",
            'edit_item' => "Edit $singular_name",
            'update_item' => "Update $singular_name",
            'view_item' => "View $singular_name",
            'separate_items_with_commas' => "Separate " . strtolower($name) . " with commas",
            'add_or_remove_items' => "Add or remove " . strtolower($name),
            'choose_from_most_used' => "Choose from the most used " . strtolower($name),
            'popular_items' => "Popular $name",
            'search_items' => "Search $name",
            'not_found' => "No " . strtolower($name) . " found",
            'no_terms' => "No $name",
            'items_list' => "$name list",
            'items_list_navigation' => "$name list navigation",
        ];

        // Merge provided labels with defaults
        $labels = array_merge($default_labels, $config['labels']);

        // Default taxonomy arguments
        $default_args = [
            'labels' => $labels,
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => sanitize_title($taxonomy_slug)],
        ];

        // Merge provided args with defaults
        $args = array_merge($default_args, $config['args'] ?? []);

        // Register the taxonomy
        $result = register_taxonomy($taxonomy_slug, static::POST_TYPE, $args);

        if (is_wp_error($result)) {
            return $result;
        }

        return true;
    }


    /**
     * Get all registered taxonomies for this post type.
     *
     * @return array Array of taxonomy objects
     */
    public function get_registered_taxonomies(): array
    {
        return get_object_taxonomies(static::POST_TYPE, 'objects');
    }

    /**
     * Get taxonomy terms for a specific post.
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy slug
     * @param array $args Additional arguments for get_the_terms()
     * @return array|false|WP_Error Array of term objects, false if no terms, WP_Error on failure
     */
    public function get_post_terms(int $post_id, string $taxonomy, array $args = []): array|false|WP_Error
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== static::POST_TYPE) {
            return new WP_Error('invalid_post', 'Post not found or invalid type');
        }

        return get_the_terms($post_id, $taxonomy);
    }

    /**
     * Set taxonomy terms for a specific post.
     *
     * @param int $post_id Post ID
     * @param string $taxonomy Taxonomy slug
     * @param array|string $terms Terms to set (IDs, slugs, or names)
     * @param bool $append Whether to append terms or replace existing ones
     * @return array|WP_Error Array of term taxonomy IDs on success, WP_Error on failure
     */
    public function set_post_terms(int $post_id, string $taxonomy, array|string $terms, bool $append = false): array|WP_Error
    {
        $post = get_post($post_id);

        if (!$post || $post->post_type !== static::POST_TYPE) {
            return new WP_Error('invalid_post', 'Post not found or invalid type');
        }

        $result = wp_set_post_terms($post_id, $terms, $taxonomy, $append);

        // Clear cache if successful
        if (!is_wp_error($result) && $this->enable_cache) {
            $this->clear_post_cache($post_id);
        }

        return $result;
    }

    /**
     * Add an action hook and track it for removal.
     *
     * @param string $hook_name Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority level
     * @param int $accepted_args Number of accepted arguments
     * @return void
     */
    protected function add_tracked_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        add_action($hook_name, $callback, $priority, $accepted_args);

        $this->registered_hooks[] = [
            'type' => 'action',
            'hook' => $hook_name,
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Add a filter hook and track it for removal.
     *
     * @param string $hook_name Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority level
     * @param int $accepted_args Number of accepted arguments
     * @return void
     */
    protected function add_tracked_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        add_filter($hook_name, $callback, $priority, $accepted_args);

        $this->registered_hooks[] = [
            'type' => 'filter',
            'hook' => $hook_name,
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Remove all tracked hooks.
     *
     * @return void
     */
    protected function remove_hooks(): void
    {
        foreach ($this->registered_hooks as $hook_info) {
            if ($hook_info['type'] === 'action') {
                remove_action($hook_info['hook'], $hook_info['callback'], $hook_info['priority']);
            } elseif ($hook_info['type'] === 'filter') {
                remove_filter($hook_info['hook'], $hook_info['callback'], $hook_info['priority']);
            }
        }
    }

    /**
     * Reset the model to its initial state.
     * 
     * This deactivates the model, clears caches, and resets internal state.
     *
     * @return Model Returns self for method chaining
     */
    public function reset(): Model
    {
        // Deactivate if running
        if ($this->is_running) {
            $this->deactivate();
        }

        // Clear validation errors
        $this->validation_errors = [];

        // Clear admin columns cache
        $this->admin_columns_cache = null;

        // Clear all caches for this model
        if ($this->enable_cache) {
            $this->clear_all_cache();
        }

        // Reset initialization state
        $this->has_been_initialized = false;

        return $this;
    }

    /**
     * Clear all cached data for this model.
     *
     * @return void
     */
    protected function clear_all_cache(): void
    {
        // Get all cache keys for this group and delete them
        $stats = Cache::get_stats($this->cache_group);
        if (isset($stats['keys'])) {
            foreach ($stats['keys'] as $key) {
                Cache::delete($key, $this->cache_group);
            }
        }
    }
}
