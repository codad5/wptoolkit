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
     * @param Config $config Configuration instance
     * @return static Singleton instance of the model
     */
    public static function get_instance(Config $config): static
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
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
     * Define custom admin columns configuration.
     * 
     * Return false to use default WordPress columns, or return an array like:
     * [
     *     'column_key' => [
     *         'label' => 'Column Label',
     *         'type' => 'text|number|date|select|custom',
     *         'sortable' => true|false|callable,
     *         'allow_quick_edit' => true|false,
     *         'get_value' => callable|string, // Function to get the value
     *         'save_value' => callable|string, // Function to save quick edit value
     *         'quick_edit_type' => 'text|number|select|date', // Override for quick edit
     *         'quick_edit_options' => [], // Options for select type
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

        $args = array_merge(static::get_post_type_args(), [
            'show_in_menu' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'public' => true,
            'has_archive' => true,
            'rewrite' => ['slug' => static::POST_TYPE],
        ]);

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
     * Render quick edit fields for custom columns.
     *
     * @param string $column_name Column name
     * @param string $post_type Post type
     * @return void
     */
    public function render_quick_edit_fields(string $column_name, string $post_type): void
    {
        if ($post_type !== static::POST_TYPE) {
            return;
        }

        $admin_columns = $this->get_admin_columns();

        if (!$admin_columns) {
            return;
        }

        echo '<fieldset class="inline-edit-col-right">';
        echo '<div class="inline-edit-col">';
        echo '<h4>' . esc_html__('Custom Fields', $this->config->get('textdomain', 'default')) . '</h4>';

        foreach ($admin_columns as $key => $config) {
            if (!($config['allow_quick_edit'] ?? false)) {
                continue;
            }

            $label = $config['label'] ?? ucfirst(str_replace('_', ' ', $key));
            $type = $config['quick_edit_type'] ?? $config['type'] ?? 'text';

            echo '<label>';
            echo '<span class="title">' . esc_html($label) . '</span>';

            switch ($type) {
                case 'select':
                    echo '<select name="' . esc_attr($key) . '">';
                    echo '<option value="">' . esc_html__('— No Change —', $this->config->get('textdomain', 'default')) . '</option>';

                    $options = $config['quick_edit_options'] ?? $config['options'] ?? [];
                    foreach ($options as $value => $option_label) {
                        echo '<option value="' . esc_attr($value) . '">' . esc_html($option_label) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'number':
                    $attrs = '';
                    if (isset($config['min'])) $attrs .= ' min="' . esc_attr($config['min']) . '"';
                    if (isset($config['max'])) $attrs .= ' max="' . esc_attr($config['max']) . '"';
                    if (isset($config['step'])) $attrs .= ' step="' . esc_attr($config['step']) . '"';

                    echo '<input type="number" name="' . esc_attr($key) . '"' . $attrs . ' />';
                    break;

                case 'date':
                    echo '<input type="date" name="' . esc_attr($key) . '" />';
                    break;

                default:
                    echo '<input type="text" name="' . esc_attr($key) . '" />';
                    break;
            }
            echo '</label>';
        }

        echo '</div>';
        echo '</fieldset>';

        // Add JavaScript to populate fields
        $this->render_quick_edit_script();
    }

    /**
     * Render JavaScript for quick edit functionality.
     *
     * @return void
     */
    private function render_quick_edit_script(): void
    {
        static $script_rendered = false;

        if ($script_rendered) {
            return;
        }

        $script_rendered = true;
        $post_type = static::POST_TYPE;

?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Populate quick edit fields
                $('a.editinline').on('click', function() {
                    var post_id = $(this).closest('tr').attr('id').replace('post-', '');
                    var $row = $('#edit-' + post_id);

                    // Get current values from the row
                    var $current_row = $('#post-' + post_id);

                    <?php
                    $admin_columns = $this->get_admin_columns();
                    if ($admin_columns) {
                        foreach ($admin_columns as $key => $config) {
                            if ($config['allow_quick_edit'] ?? false) {
                                echo "
                            var {$key}_value = \$current_row.find('td.{$key} .hidden').text() || \$current_row.find('td.{$key}').text();
                            \$row.find('input[name=\"{$key}\"], select[name=\"{$key}\"]').val({$key}_value);
                            ";
                            }
                        }
                    }
                    ?>
                });

                // Handle quick edit save
                $('#bulk-edit').on('click', '#bulk_edit', function() {
                    var $bulk_row = $('#bulk-edit');
                    var post_ids = [];

                    $bulk_row.find('#bulk-titles').children().each(function() {
                        post_ids.push($(this).attr('id').replace(/^(ttle)/i, ''));
                    });

                    var data = {
                        action: 'save_<?php echo esc_js($post_type); ?>_quick_edit',
                        post_ids: post_ids,
                        _wpnonce: $('#_wpnonce').val()
                    };

                    <?php
                    if ($admin_columns) {
                        foreach ($admin_columns as $key => $config) {
                            if ($config['allow_quick_edit'] ?? false) {
                                echo "data.{$key} = \$bulk_row.find('input[name=\"{$key}\"], select[name=\"{$key}\"]').val();";
                            }
                        }
                    }
                    ?>

                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    });
                });
            });
        </script>
<?php
    }

    /**
     * Handle quick edit AJAX save.
     *
     * @return void
     */
    public function handle_quick_edit_save(): void
    {
        check_ajax_referer('bulk-posts');

        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to edit posts.', $this->config->get('textdomain', 'default')));
        }

        $post_ids = array_map('intval', $_POST['post_ids'] ?? []);
        $admin_columns = $this->get_admin_columns();

        if (!$admin_columns) {
            wp_send_json_error('No quick edit fields configured');
        }

        $updated = 0;

        foreach ($post_ids as $post_id) {
            if (!$post_id || get_post_type($post_id) !== static::POST_TYPE) {
                continue;
            }

            foreach ($admin_columns as $key => $config) {
                if (!($config['allow_quick_edit'] ?? false)) {
                    continue;
                }

                $value = sanitize_text_field($_POST[$key] ?? '');

                if (empty($value)) {
                    continue;
                }

                if ($this->save_column_value($post_id, $key, $value, $config)) {
                    $updated++;
                }
            }

            // Clear cache
            if ($this->enable_cache) {
                $this->clear_post_cache($post_id);
            }
        }

        wp_send_json_success([
            'message' => sprintf(__('Updated %d posts successfully.', $this->config->get('textdomain', 'default')), $updated)
        ]);
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

        // Default: try to get from meta
        $meta_key = $this->get_meta_key_for_column($column_key, $config);
        return get_post_meta($post_id, $meta_key, true);
    }

    /**
     * Save column value from quick edit.
     *
     * @param int $post_id Post ID
     * @param string $column_key Column key
     * @param mixed $value Value to save
     * @param array $config Column configuration
     * @return bool Success status
     */
    protected function save_column_value(int $post_id, string $column_key, mixed $value, array $config): bool
    {
        if (isset($config['save_value'])) {
            if (is_callable($config['save_value'])) {
                return call_user_func($config['save_value'], $post_id, $value);
            }

            if (is_string($config['save_value']) && method_exists($this, $config['save_value'])) {
                return $this->{$config['save_value']}($post_id, $value);
            }
        }

        // Default: save to meta
        $meta_key = $this->get_meta_key_for_column($column_key, $config);
        return update_post_meta($post_id, $meta_key, $value) !== false;
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

        return static::META_PREFIX . $column_key;
    }

    /**
     * Get a MetaBox by ID.
     *
     * @param string $metabox_id MetaBox identifier
     * @return MetaBox|null MetaBox instance or null if not found
     */
    public function getMetabox(string $metabox_id): ?MetaBox
    {
        foreach ($this->metaBoxes as $metabox) {
            if (property_exists($metabox, 'id') && $metabox->id === $metabox_id) {
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
                    'metabox_id' => property_exists($metaBox, 'id') ? $metaBox->id : 'unknown'
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

        // Save metadata
        if (!empty($meta_data)) {
            $this->save_meta($post_id, $meta_data);
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

        // Update metadata
        if (!empty($meta_data)) {
            $this->save_meta($post_id, $meta_data);
        }

        // Clear cache
        if ($this->enable_cache) {
            $this->clear_post_cache($post_id);
        }

        return true;
    }

    /**
     * Get a post by ID with metadata.
     *
     * @param int $post_id Post ID
     * @param bool $include_meta Whether to include metadata
     * @return array|null Post data array or null if not found
     */
    public function get_post(int $post_id, bool $include_meta = true): ?array
    {
        // Check cache first
        if ($this->enable_cache) {
            $cache_key = "post_{$post_id}_" . ($include_meta ? 'with_meta' : 'no_meta');
            $cached = Cache::get($cache_key, null, $this->cache_group);

            if ($cached !== null) {
                return $cached;
            }
        }

        $post = get_post($post_id);

        if (!$post || $post->post_type !== static::POST_TYPE) {
            return null;
        }

        $result = [
            'post' => $post,
            'meta' => []
        ];

        if ($include_meta) {
            $result['meta'] = $this->get_post_meta($post_id);
        }

        // Cache the result
        if ($this->enable_cache) {
            Cache::set($cache_key, $result, $this->cache_duration, $this->cache_group);
        }

        return $result;
    }

    /**
     * Get posts with optional filtering and metadata.
     *
     * @param array $args Query arguments
     * @param bool $include_meta Whether to include metadata for each post
     * @return array Array of post data
     */
    public function get_posts(array $args = [], bool $include_meta = false): array
    {
        // Set post type in query args
        $args['post_type'] = static::POST_TYPE;

        // Set default number of posts
        if (!isset($args['posts_per_page'])) {
            $args['posts_per_page'] = 10;
        }

        // Check cache for simple queries
        $cache_key = null;
        if ($this->enable_cache && count($args) <= 3) { // Simple query
            $cache_key = 'posts_' . md5(serialize($args)) . '_' . ($include_meta ? 'with_meta' : 'no_meta');
            $cached = Cache::get($cache_key, null, $this->cache_group);

            if ($cached !== null) {
                return $cached;
            }
        }

        $posts = get_posts($args);
        $results = [];

        foreach ($posts as $post) {
            $post_data = ['post' => $post];

            if ($include_meta) {
                $post_data['meta'] = $this->get_post_meta($post->ID);
            }

            $results[] = $post_data;
        }

        // Cache simple queries
        if ($cache_key) {
            Cache::set($cache_key, $results, $this->cache_duration, $this->cache_group);
        }

        return $results;
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
     * Search posts by title, content, or metadata.
     *
     * @param string $search_term Search term
     * @param array $search_fields Fields to search in ('title', 'content', 'meta')
     * @param array $args Additional query arguments
     * @return array Search results
     */
    public function search(string $search_term, array $search_fields = ['title', 'content'], array $args = []): array
    {
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

        // Handle title/content search
        if (array_intersect(['title', 'content'], $search_fields)) {
            $args['s'] = $search_term;
        }

        $query = new WP_Query($args);
        $results = [];

        foreach ($query->posts as $post) {
            $results[] = [
                'post' => $post,
                'meta' => $this->get_post_meta($post->ID),
                'relevance' => $this->calculate_relevance($post, $search_term, $search_fields)
            ];
        }

        // Sort by relevance
        usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        return $results;
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
     * Handle AJAX search requests.
     *
     * @return void
     */
    public function handle_ajax_search(): void
    {
        check_ajax_referer(static::POST_TYPE . '_search', 'nonce');

        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $search_fields = array_map('sanitize_text_field', $_POST['fields'] ?? ['title', 'content']);

        if (empty($search_term)) {
            wp_send_json_error('Search term is required');
        }

        $results = $this->search($search_term, $search_fields, [
            'posts_per_page' => 10
        ]);

        wp_send_json_success($results);
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
     * @return array Organized metadata
     */
    protected function get_post_meta(int $post_id): array
    {
        $meta = [];

        foreach ($this->metaBoxes as $metaBox) {
            $metabox_data = $metaBox->all_meta($post_id);
            $metabox_id = property_exists($metaBox, 'id') ? $metaBox->id : 'unknown';
            $meta[$metabox_id] = $metabox_data;
        }

        return $meta;
    }

    /**
     * Save metadata for a post.
     *
     * @param int $post_id Post ID
     * @param array $meta_data Metadata to save
     * @return void
     */
    protected function save_meta(int $post_id, array $meta_data): void
    {
        foreach ($meta_data as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

    /**
     * Validate fields using registered MetaBoxes.
     *
     * @param array $data Data to validate
     * @return bool Validation result
     */
    protected function validate_fields(array $data): bool
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

        return empty($this->validation_errors);
    }

    /**
     * Calculate search relevance score.
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

        // Title relevance (highest weight)
        if (in_array('title', $search_fields)) {
            $title_lower = strtolower($post->post_title);
            if (str_contains($title_lower, $term_lower)) {
                $score += 10;
                if ($title_lower === $term_lower) {
                    $score += 20; // Exact match bonus
                }
            }
        }

        // Content relevance
        if (in_array('content', $search_fields)) {
            $content_lower = strtolower($post->post_content);
            $score += substr_count($content_lower, $term_lower) * 2;
        }

        // Meta relevance
        if (in_array('meta', $search_fields)) {
            $meta = get_post_meta($post->ID);
            foreach ($meta as $values) {
                foreach ($values as $value) {
                    if (str_contains(strtolower($value), $term_lower)) {
                        $score += 1;
                    }
                }
            }
        }

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
        // Clear specific post caches
        Cache::delete("post_{$post_id}_with_meta", $this->cache_group);
        Cache::delete("post_{$post_id}_no_meta", $this->cache_group);

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
     * Enhanced setup_hooks method that tracks registered hooks for removal.
     *
     * @return void
     */
    protected function setup_hooks(): void
    {
        // Store hook information for later removal
        $this->add_tracked_action('init', [$this, 'register_post_type']);
        $this->add_tracked_action('save_post_' . static::POST_TYPE, [$this, 'save_post'], 10, 2);
        $this->add_tracked_action('delete_post', [$this, 'handle_post_deletion']);

        // Admin columns hooks
        $this->add_tracked_filter('manage_' . static::POST_TYPE . '_posts_columns', [$this, 'setup_admin_columns']);
        $this->add_tracked_action('manage_' . static::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_column'], 10, 2);
        $this->add_tracked_filter('manage_edit-' . static::POST_TYPE . '_sortable_columns', [$this, 'setup_sortable_columns']);
        $this->add_tracked_action('pre_get_posts', [$this, 'handle_column_sorting']);

        // Quick edit hooks
        $this->add_tracked_action('quick_edit_custom_box', [$this, 'render_quick_edit_fields'], 10, 2);
        $this->add_tracked_action('wp_ajax_save_' . static::POST_TYPE . '_quick_edit', [$this, 'handle_quick_edit_save']);

        // Authentication hooks
        if (static::REQUIRES_AUTHENTICATION) {
            $this->add_tracked_action('template_redirect', [$this, 'handle_authentication']);
        }

        // AJAX search
        $this->add_tracked_action('wp_ajax_' . static::POST_TYPE . '_search', [$this, 'handle_ajax_search']);
        $this->add_tracked_action('wp_ajax_nopriv_' . static::POST_TYPE . '_search', [$this, 'handle_ajax_search']);
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
