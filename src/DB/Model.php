<?php

/**
 * Abstract Model
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\DB;

use Codad5\WPToolkit\Utils\{Config, Cache, Notification, InputValidator};
use WP_Post;
use WP_Error;
use WP_Query;
use Exception;

/**
 * Abstract Model class for WordPress custom post types with MetaBox integration.
 * 
 * Provides a complete foundation for creating WordPress models with:
 * - Custom post type registration
 * - MetaBox management and integration
 * - CRUD operations with caching
 * - Field validation and sanitization
 * - REST API integration support
 * - Advanced querying capabilities
 */
abstract class Model
{
    /**
     * Post type identifier - must be defined in child classes.
     */
    protected const POST_TYPE = '';

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
     * Constructor.
     *
     * @param Config $config Configuration instance
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->cache_group = static::POST_TYPE . '_model';
        $this->setup_hooks();
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
     * Get post type arguments for registration.
     * 
     * Child classes should override this to customize post type settings.
     *
     * @return array Post type arguments
     */
    abstract protected static function get_post_type_args(): array;

    /**
     * Setup WordPress hooks for the model.
     *
     * @return void
     */
    protected function setup_hooks(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('save_post_' . static::POST_TYPE, [$this, 'save_post'], 10, 2);
        add_action('delete_post', [$this, 'handle_post_deletion']);
        add_action('wp_ajax_' . static::POST_TYPE . '_search', [$this, 'handle_ajax_search']);
        add_action('wp_ajax_nopriv_' . static::POST_TYPE . '_search', [$this, 'handle_ajax_search']);
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
     * Get a specific MetaBox by ID.
     *
     * @param string $metabox_id MetaBox identifier
     * @return MetaBox|null MetaBox instance or null if not found
     */
    public function get_metabox(string $metabox_id): ?MetaBox
    {
        foreach ($this->metaBoxes as $metabox) {
            if (property_exists($metabox, 'id') && $metabox->id === $metabox_id) {
                return $metabox;
            }
        }
        return null;
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
        foreach ($stats['keys'] as $key) {
            if (str_contains($key, 'posts_')) {
                Cache::delete($key, $this->cache_group);
            }
        }
    }
}
