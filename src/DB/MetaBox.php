<?php

/**
 * Enhanced MetaBox Class
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\DB;

use Codad5\WPToolkit\Utils\{Config, ViewLoader, Cache, InputValidator};
use WP_Post;
use WP_Error;

/**
 * MetaBox class for creating and managing WordPress custom meta boxes.
 * 
 * Features:
 * - Advanced field types with validation
 * - Callback system for lifecycle events
 * - WordPress hooks integration
 * - Quick edit support
 * - AJAX functionality
 * - Template system integration
 * - Caching support
 */
class MetaBox
{
    /** @var string Unique identifier for the meta box */
    readonly string $id;

    /** @var string Title displayed at the top of the meta box */
    private string $title;

    /** @var string Post type or screen where the meta box appears */
    private string $screen;

    /** @var string Nonce field name for security verification */
    private string $nonce;

    /** @var string Context where the meta box appears */
    private string $context = 'normal';

    /** @var string Priority of the meta box */
    private string $priority = 'default';

    /** @var array HTML generators for different input types */
    private array $input_type_html = [];

    /** @var ?WP_Post Current post object */
    public ?WP_Post $post = null;

    /** @var \Closure Callback function for rendering meta box content */
    private \Closure $customise_callback;

    /** @var array Collection of field configurations */
    private array $fields = [];

    /** @var string Meta key prefix */
    private string $meta_prefix = '';

    /** @var Config Configuration instance */
    private ?Config $config = null;

    /** @var array Lifecycle callbacks */
    private array $callbacks = [
        'on_error' => [],
        'on_success' => [],
        'on_pre_save' => [],
        'on_post_save' => [],
        'on_pre_validate' => [],
        'on_post_validate' => []
    ];

    /** @var array Last validation errors */
    private array $last_errors = [];

    /** @var bool Enable caching for field values */
    private bool $enable_cache = false;

    /** @var int Cache duration in seconds */
    private int $cache_duration = 3600;

    /** @var array Custom sanitizers */
    private array $custom_sanitizers = [];

    // private constant for input view base path
    private const INPUT_VIEW_BASE = __DIR__ . '/../../views/forms/';

    /**
     * Constructor for the MetaBox class.
     *
     * @param string $id Unique identifier for the meta box
     * @param string $title Title displayed at the top of the meta box
     * @param string $screen Post type or screen where the meta box appears
     * @param Config|null $config Optional configuration instance
     */
    public function __construct(string $id, string $title, string $screen, ?Config $config = null)
    {
        $this->id = sanitize_key($id);
        $this->title = $title;
        $this->screen = $screen;
        $this->config = $config;
        $this->nonce = $this->id . '-nonce';
        $this->meta_prefix = "{$this->id}_{$this->screen}_";
        $this->customise_callback = fn($post) => $this->default_callback($post);

        $this->setup_default_sanitizers();
    }

    /**
     * Create a new MetaBox instance with fluent interface.
     *
     * @param string $id MetaBox ID
     * @param string $title MetaBox title
     * @param string $screen Post type
     * @param Config|null $config Configuration instance
     * @return self
     */
    public static function create(string $id, string $title, string $screen, ?Config $config = null): self
    {
        return new self($id, $title, $screen, $config);
    }

    /**
     * Generic setter for class properties with fluent interface.
     *
     * @param string $property Property name
     * @param mixed $value Property value
     * @return self
     */
    public function set(string $property, mixed $value): self
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }
        return $this;
    }

    /**
     * Set custom nonce key.
     *
     * @param string $nonce Custom nonce key
     * @return self
     */
    public function set_nonce(string $nonce): self
    {
        $this->nonce = sanitize_key($nonce);
        return $this;
    }

    /**
     * Set meta key prefix.
     *
     * @param string $prefix Meta key prefix
     * @return self
     */
    public function set_prefix(string $prefix): self
    {
        $this->meta_prefix = sanitize_key($prefix);
        return $this;
    }

    // get prefix
    /**
     * Get the current meta key prefix.
     *
     * @return string
     */
    public function get_prefix(): string
    {
        return $this->meta_prefix;
    }

    /**
     * Enable or disable caching.
     *
     * @param bool $enable Enable caching
     * @param int $duration Cache duration in seconds
     * @return self
     */
    public function set_caching(bool $enable, int $duration = 3600): self
    {
        $this->enable_cache = $enable;
        $this->cache_duration = $duration;
        return $this;
    }

    /**
     * Setup WordPress actions and hooks.
     *
     * @return self
     */
    public function setup_actions(): self
    {
        add_action("add_meta_boxes_{$this->screen}", [$this, 'show']);
        add_action('quick_edit_custom_box', [$this, 'show_quick_edit_field'], 10, 2);
        add_action("wp_ajax_wptoolkit_metabox_{$this->id}_fetch_data", [$this, 'handle_ajax']);
        add_action("wp_ajax_nopriv_wptoolkit_metabox_{$this->id}_fetch_data", [$this, 'handle_ajax']);

        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_media();
            $this->enqueue_scripts();
        });

        return $this;
    }

    /**
     * Add callback for lifecycle events.
     *
     * @param string $event Event name (error, success, pre_save, post_save, pre_validate, post_validate)
     * @param callable $callback Callback function
     * @return self
     */
    public function on(string $event, callable $callback): self
    {
        $event_key = 'on_' . $event;
        if (array_key_exists($event_key, $this->callbacks)) {
            $this->callbacks[$event_key][] = $callback;
        }
        return $this;
    }

    /**
     * Shorthand methods for common callbacks.
     */
    public function onError(callable $callback): self
    {
        return $this->on('error', $callback);
    }

    public function onSuccess(callable $callback): self
    {
        return $this->on('success', $callback);
    }

    public function onPreSave(callable $callback): self
    {
        return $this->on('pre_save', $callback);
    }

    public function onPostSave(callable $callback): self
    {
        return $this->on('post_save', $callback);
    }

    public function onPreValidate(callable $callback): self
    {
        return $this->on('pre_validate', $callback);
    }

    public function onPostValidate(callable $callback): self
    {
        return $this->on('post_validate', $callback);
    }

    /**
     * Add the meta box to WordPress admin.
     *
     * @param WP_Post|null $post Current post object
     * @return void
     */
    public function show(WP_Post $post = null): void
    {
        $this->post = $post;

        add_meta_box(
            $this->id,
            $this->title,
            fn($post) => $this->customise_callback->__invoke($post),
            $this->screen,
            $this->context,
            $this->priority
        );
    }

    /**
     * Show quick edit fields.
     *
     * @param string $column_name Column name
     * @param string $post_type Post type
     * @return void
     */
    public function show_quick_edit_field(string $column_name, string $post_type): void
    {
        if ($post_type !== $this->screen) {
            return;
        }

        static $nonce_printed = false;
        if (!$nonce_printed) {
            $nonce_printed = true;
            wp_nonce_field(basename(__FILE__), $this->nonce);
            wp_nonce_field(basename(__FILE__), $this->get_quick_edit_nonce());
        }

        $field = $this->get_field('id', $column_name);
        if (!$field || !($field['allow_quick_edit'] ?? false)) {
            return;
        }

        $field['default'] = $this->get_field_value($field['id']);
        $this->render_field($field['type'], $field['id'], $field);
    }

    /**
     * Enqueue necessary scripts and styles.
     *
     * @return void
     */
    private function enqueue_scripts(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== $this->screen) {
            return;
        }

        $quick_edit_fields = array_filter(
            $this->fields,
            fn($field) => $field['allow_quick_edit'] ?? false
        );

        if (empty($quick_edit_fields) || $screen->base !== 'edit') {
            return;
        }

        $script_url = $this->config
            ? $this->config->url('assets/js/metabox-quick-edit.js')
            : admin_url('assets/js/metabox-quick-edit.js');

        wp_enqueue_script(
            "wptoolkit-metabox-{$this->id}",
            $script_url,
            ['jquery', 'inline-edit-post'],
            $this->config ? $this->config->get('version') : '1.0.0',
            true
        );

        wp_localize_script("wptoolkit-metabox-{$this->id}", 'wptoolkitMetabox', [
            'quick_edit_fields' => array_values(array_map(fn($f) => $f['id'], $quick_edit_fields)),
            'metabox_id' => $this->id,
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->get_fetch_data_nonce())
        ]);
    }

    /**
     * Add a field to the meta box.
     *
     * @param string $id Field ID
     * @param string $label Field label
     * @param string $type Field type
     * @param array $options Field options (for select, radio, checkbox)
     * @param array $attributes HTML attributes
     * @param array $config Additional field configuration
     * @return self
     */
    public function add_field(
        string $id,
        string $label,
        string $type,
        array $options = [],
        array $attributes = [],
        array $config = []
    ): self {
        if (!empty($this->meta_prefix) && !str_starts_with($id, $this->meta_prefix)) {
            $id = $this->meta_prefix . $id;
        }

        $this->fields[] = array_merge($config, [
            'id' => $id,
            'label' => $label,
            'type' => $type,
            'options' => $options,
            'attributes' => $attributes,
            'default' => $attributes['value'] ?? $config['default'] ?? '',
            'allow_quick_edit' => $config['allow_quick_edit'] ?? false,
            'description' => $config['description'] ?? '',
            'required' => $config['required'] ?? $attributes['required'] ?? false,
            'sanitize_callback' => $config['sanitize_callback'] ?? null,
            'validate_callback' => $config['validate_callback'] ?? null
        ]);

        return $this;
    }

    /**
     * Add multiple fields at once.
     *
     * @param array $fields Array of field configurations
     * @return self
     */
    public function add_fields(array $fields): self
    {
        foreach ($fields as $field) {
            $this->add_field(
                $field['id'],
                $field['label'],
                $field['type'],
                $field['options'] ?? [],
                $field['attributes'] ?? [],
                $field['config'] ?? []
            );
        }
        return $this;
    }

    /**
     * Set custom callback for meta box content rendering.
     *
     * @param \Closure $callback Render callback
     * @return self
     */
    public function set_callback(\Closure $callback): self
    {
        $this->customise_callback = $callback;
        return $this;
    }

    /**
     * Default callback for rendering meta box content.
     *
     * @param WP_Post|null $post Current post object
     * @return void
     */
    public function default_callback(WP_Post $post = null): void
    {
        wp_nonce_field(basename(__FILE__), $this->nonce);

        echo '<div class="wptoolkit-metabox-wrap">';

        foreach ($this->fields as $field) {
            $is_multiple = isset($field['attributes']['multiple']) && $field['attributes']['multiple'];
            $value = get_post_meta($post->ID, $field['id'], !$is_multiple);
            $field['default'] = $value;

            $this->render_field($field['type'], $field['id'], $field);
        }

        echo '</div>';
    }

    /**
     * Save meta box field values.
     *
     * @param int $post_id Post ID
     * @return bool|WP_Error Success status or error
     */
    public function save(int $post_id): bool|WP_Error
    {
        try {
            // Pre-save hook
            do_action("wptoolkit_metabox_{$this->id}_pre_save", $post_id, $this);
            $this->fire_callbacks('on_pre_save', [$post_id, $this]);

            // Check permissions and nonces
            $validation_result = $this->validate_save_request($post_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Determine which fields to process
            $fields_to_process = $this->get_fields_to_process();

            if (empty($fields_to_process)) {
                return true;
            }

            // Pre-validate hook
            do_action("wptoolkit_metabox_{$this->id}_pre_validate", $post_id, $fields_to_process, $this);
            $this->fire_callbacks('on_pre_validate', [$post_id, $fields_to_process, $this]);

            // Validate fields
            $validation_errors = $this->validate_fields($fields_to_process);

            // Post-validate hook
            do_action("wptoolkit_metabox_{$this->id}_post_validate", $post_id, $fields_to_process, $validation_errors, $this);
            $this->fire_callbacks('on_post_validate', [$post_id, $fields_to_process, $validation_errors, $this]);

            if (!empty($validation_errors)) {
                $this->last_errors = $validation_errors;

                // Fire error callbacks and hooks
                do_action("wptoolkit_metabox_{$this->id}_validation_failed", $post_id, $validation_errors, $this);
                $this->fire_callbacks('on_error', [$validation_errors, $post_id, $this]);

                return new WP_Error('validation_failed', 'Field validation failed', $validation_errors);
            }

            // Save fields
            $save_result = $this->save_fields($post_id, $fields_to_process);

            if ($save_result) {
                // Clear cache if enabled
                if ($this->enable_cache) {
                    $this->clear_field_cache($post_id);
                }

                // Fire success callbacks and hooks
                do_action("wptoolkit_metabox_{$this->id}_save_success", $post_id, $this);
                $this->fire_callbacks('on_success', [$post_id, $this]);
            }

            // Post-save hook
            do_action("wptoolkit_metabox_{$this->id}_post_save", $post_id, $save_result, $this);
            $this->fire_callbacks('on_post_save', [$post_id, $save_result, $this]);

            return $save_result;
        } catch (\Throwable $e) {
            $error = new WP_Error('save_exception', 'Error saving meta box: ' . $e->getMessage());
            $this->fire_callbacks('on_error', [$error, $post_id, $this]);
            return $error;
        }
    }

    /**
     * Get all meta values for a post.
     *
     * @param int $post_id Post ID
     * @param string|null $strip Prefix to strip from meta keys
     * @return array Meta values
     */
    public function all_meta(int $post_id, string|bool $strip = null): array
    {
        if ($this->enable_cache) {
            $cache_key = "metabox_{$this->id}_meta_{$post_id}";
            $cached = Cache::get($cache_key, null, 'wptoolkit_metabox');

            if ($cached !== null) {
                return $cached;
            }
        }

        $meta = [];
        $post_meta = get_post_meta($post_id);

        $strip = $strip === true ? $this->meta_prefix : $strip;
        foreach ($this->fields as $field) {
            $key = $strip ? str_replace($strip, '', $field['id']) : $field['id'];
            $value = ($field['attributes']['multiple'] ?? false) ? $post_meta[$field['id']] : $post_meta[$field['id']][0] ?? null;

            // Apply reverse sanitization if needed
            if ($value !== null) {
                $value = $this->reverse_sanitize_field_value($value, $field);
            }

            $meta[$key] = $value;
        }

        if ($this->enable_cache) {
            Cache::set($cache_key, $meta, $this->cache_duration, 'wptoolkit_metabox');
        }

        return $meta;
    }

    /**
     * Get a specific field configuration.
     *
     * @param string $by Property to search by
     * @param mixed $value Value to match
     * @return array|null Field configuration or null
     */
    public function get_field(string $by, mixed $value): ?array
    {
        if ($by === 'id' && !empty($this->meta_prefix) && !str_starts_with($value, $this->meta_prefix)) {
            $value = $this->meta_prefix . $value;
        }

        foreach ($this->fields as $field) {
            if (isset($field[$by]) && $field[$by] === $value) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Get all registered fields.
     *
     * @return array Field configurations
     */
    public function get_fields(): array
    {
        return $this->fields;
    }

    /**
     * Get field value for a post.
     *
     * @param string $field_id Field ID
     * @param int|null $post_id Post ID
     * @param bool $single Return single value
     * @return mixed Field value
     */
    public function get_field_value(string $field_id, ?int $post_id = null, bool $single = true): mixed
    {
        if ($post_id === null) {
            $post_id = $this->post?->ID ?? get_the_ID();
            if (!$post_id) {
                return null;
            }
        }

        $field = $this->get_field('id', $field_id);
        if (!$field) {
            return null;
        }

        if (!empty($this->meta_prefix) && !str_starts_with($field_id, $this->meta_prefix)) {
            $field_id = $this->meta_prefix . $field_id;
        }

        if ($this->enable_cache) {
            $cache_key = "metabox_{$this->id}_field_{$field_id}_{$post_id}";
            $cached = Cache::get($cache_key, null, 'wptoolkit_metabox');

            if ($cached !== null) {
                return $cached;
            }
        }

        $value = get_post_meta($post_id, $field_id, $single);

        if (($value === '' || $value === false) && isset($field['default'])) {
            $value = $field['default'];
        }

        // Special handling for wp_media type
        if ($field['type'] === 'wp_media') {
            if ($single) {
                $value = wp_get_attachment_url($value) ?: $value;
            } else {
                $value = array_map(fn($id) => wp_get_attachment_url($id) ?: $id, $value);
            }
        }

        if ($this->enable_cache) {
            Cache::set($cache_key, $value, $this->cache_duration, 'wptoolkit_metabox');
        }

        return $value;
    }

    /**
     * Get last validation errors.
     *
     * @return array Validation errors
     */
    public function get_last_errors(): array
    {
        return $this->last_errors;
    }

    /**
     * Register custom sanitizer for a field type.
     *
     * @param string $type Field type
     * @param callable $sanitizer Sanitizer function
     * @return self
     */
    public function register_sanitizer(string $type, callable $sanitizer): self
    {
        $this->custom_sanitizers[$type] = $sanitizer;
        return $this;
    }

    /**
     * Set custom HTML generator for input type.
     *
     * @param string $type Input type
     * @param \Closure $callback HTML generator callback
     * @return self
     */
    public function set_input_type_html(string $type, \Closure $callback): self
    {
        $this->input_type_html[$type] = $callback;
        return $this;
    }

    /**
     * Handle AJAX requests.
     *
     * @return void
     */
    public function handle_ajax(): void
    {
        check_ajax_referer($this->get_fetch_data_nonce(), 'nonce');

        $post_id = absint($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error('Invalid post ID');
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }

        wp_send_json_success($this->all_meta($post_id));
    }

    /**
     * Get quick edit nonce key.
     *
     * @return string
     */
    private function get_quick_edit_nonce(): string
    {
        return "wptoolkit-metabox-quick-edit-{$this->nonce}";
    }

    /**
     * Get fetch data nonce key.
     *
     * @return string
     */
    private function get_fetch_data_nonce(): string
    {
        return "wptoolkit-metabox-fetch-data-{$this->nonce}";
    }

    /**
     * Validate save request permissions and nonces.
     *
     * @param int $post_id Post ID
     * @return bool|WP_Error
     */
    private function validate_save_request(int $post_id): bool|WP_Error
    {
        $is_quick_edit = false;

        // Check quick edit nonce
        $quick_edit_nonce = sanitize_text_field($_POST[$this->get_quick_edit_nonce()] ?? '');
        if (wp_verify_nonce($quick_edit_nonce, basename(__FILE__))) {
            $is_quick_edit = true;
        }

        // Skip for autosave unless quick edit
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE && !$is_quick_edit) {
            return new WP_Error('autosave_skip', 'Skipping autosave');
        }

        // Skip for AJAX unless quick edit
        if (defined('DOING_AJAX') && DOING_AJAX && !$is_quick_edit) {
            return new WP_Error('ajax_skip', 'Skipping AJAX save');
        }

        // Check user capabilities
        if (!current_user_can('edit_post', $post_id)) {
            return new WP_Error('insufficient_permissions', 'Insufficient permissions to edit post');
        }

        // Check main nonce
        if (!isset($_POST[$this->nonce])) {
            return new WP_Error('missing_nonce', 'Missing nonce field');
        }

        $nonce = sanitize_text_field($_POST[$this->nonce]);
        if (!wp_verify_nonce($nonce, basename(__FILE__))) {
            return new WP_Error('invalid_nonce', 'Invalid nonce');
        }

        return true;
    }

    /**
     * Get fields to process based on context.
     *
     * @return array
     */
    private function get_fields_to_process(): array
    {
        $is_quick_edit = wp_verify_nonce(
            sanitize_text_field($_POST[$this->get_quick_edit_nonce()] ?? ''),
            basename(__FILE__)
        );

        if ($is_quick_edit) {
            return array_filter($this->fields, fn($field) => $field['allow_quick_edit'] ?? false);
        }

        return $this->fields;
    }

    /**
     * Validate fields using InputValidator.
     *
     * @param array $fields Fields to validate
     * @return array Validation errors
     */
    private function validate_fields(array $fields): array
    {
        $errors = [];

        foreach ($fields as $field) {
            $field_id = $field['id'];
            $value = $_POST[$field_id] ?? null;

            // Skip validation for empty optional fields
            if (empty($value) && !($field['required'] ?? false)) {
                continue;
            }

            $result = InputValidator::validate($field['type'], $value, $field);

            if ($result !== true) {
                $errors[$field_id] = is_string($result) ? $result : 'Validation failed';
            }
        }

        return $errors;
    }

    /**
     * Save field values to post meta.
     *
     * @param int $post_id Post ID
     * @param array $fields Fields to save
     * @return bool
     */
    private function save_fields(int $post_id, array $fields): bool
    {
        $success = true;

        foreach ($fields as $field) {
            $field_id = $field['id'];
            $value = $_POST[$field_id] ?? '';
            $this->save_field($post_id, $field_id, $value);
        }

        return $success;
    }

    // a method for saving a single field value
    /**
     * Save a single field value.
     *
     * @param int $post_id Post ID
     * @param string $field_id Field ID
     * @param mixed $value Field value
     * @return bool
     */
    public function save_field(int $post_id, string $field_id, mixed $value): bool
    {
        $field = $this->get_field('id', $field_id);
        if (!$field) {
            return false; // Field not found
        }

        // Sanitize the value
        $sanitized_value = $this->sanitize_field_value($value, $field);

        // Handle wp_media fields specially
        if ($field['type'] === 'wp_media') {
            return $this->save_media_field($post_id, $field_id, $sanitized_value);
        } else {
            return $this->save_regular_field($post_id, $field_id, $sanitized_value);
        }
    }

    /**
     * Save media field values.
     *
     * @param int $post_id Post ID
     * @param string $field_id Field ID
     * @param mixed $value Field value
     * @return bool
     */
    private function save_media_field(int $post_id, string $field_id, mixed $value): bool
    {
        $media_ids = is_array($value) ? $value : [$value];
        $sanitized_ids = array_map('absint', array_filter($media_ids));

        // Delete existing meta
        delete_post_meta($post_id, $field_id);

        // Add new values
        foreach ($sanitized_ids as $media_id) {
            if ($media_id > 0) {
                add_post_meta($post_id, $field_id, $media_id);
            }
        }

        return true;
    }

    /**
     * Save regular field value.
     *
     * @param int $post_id Post ID
     * @param string $field_id Field ID
     * @param mixed $value Field value
     * @return bool
     */
    private function save_regular_field(int $post_id, string $field_id, mixed $value): bool
    {
        $exists = metadata_exists('post', $post_id, $field_id);

        if ($exists) {
            $old_value = get_post_meta($post_id, $field_id, true);
            if ($old_value !== $value) {
                return update_post_meta($post_id, $field_id, $value) !== false;
            }
        } else {
            return add_post_meta($post_id, $field_id, $value, true) !== false;
        }

        return true;
    }



    /**
     * Sanitize field value based on type and custom sanitizers.
     *
     * @param mixed $value Value to sanitize
     * @param array $field Field configuration
     * @return mixed Sanitized value
     */
    private function sanitize_field_value(mixed $value, array $field): mixed
    {
        // Use custom sanitizer if provided
        if (isset($field['sanitize_callback']) && is_callable($field['sanitize_callback'])) {
            return call_user_func($field['sanitize_callback'], $value);
        }

        // Use registered custom sanitizer
        if (isset($this->custom_sanitizers[$field['type']])) {
            return call_user_func($this->custom_sanitizers[$field['type']], $value);
        }

        // Use default sanitization
        return match ($field['type']) {
            'number' => absint($value),
            'email' => sanitize_email($value),
            'url' => esc_url_raw($value),
            'textarea' => sanitize_textarea_field($value),
            'wp_media' => is_array($value) ? array_map('absint', $value) : absint($value),
            default => sanitize_text_field($value)
        };
    }

    /**
     * Reverse sanitize field value.
     * @param mixed $value Value to reverse sanitize
     * @param array $field Field configuration
     * @return mixed Reverse sanitized value
     */
    public function reverse_sanitize_field_value(mixed $value, array $field): mixed
    {
        // Use custom reverse sanitizer if provided
        if (isset($field['reverse_sanitize_callback']) && is_callable($field['reverse_sanitize_callback'])) {
            return call_user_func($field['reverse_sanitize_callback'], $value);
        }

        // Default reverse sanitization
        return match ($field['type']) {
            'wp_media' => ($field['attributes']['multiple'] ?? false) && is_array($value)
                ? array_map(fn($id) => wp_get_attachment_url($id) ?: $id, (array)$value)
                : wp_get_attachment_url($value),
            default => $value // For other types, just return the value as is
        };
    }

    /**
     * Fire callbacks for an event.
     *
     * @param string $event Event name
     * @param array $args Callback arguments
     * @return void
     */
    private function fire_callbacks(string $event, array $args = []): void
    {
        foreach ($this->callbacks[$event] as $callback) {
            if (is_callable($callback)) {
                call_user_func_array($callback, $args);
            }
        }
    }

    /**
     * Render a field.
     *
     * @param string $type Field type
     * @param string $id Field ID
     * @param array $data Field data
     * @return void
     */
    private function render_field(string $type, string $id, array $data): void
    {
        if (empty($this->input_type_html)) {
            $this->setup_input_type_html();
        }

        if (isset($this->input_type_html[$type])) {
            echo $this->input_type_html[$type]($id, $data);
        } else {
            $this->render_default_field($type, $id, $data);
        }
    }

    /**
     * Setup default input type HTML generators.
     *
     * @return void
     */
    private function setup_input_type_html(): void
    {
        $types = [
            'text',
            'textarea',
            'select',
            'checkbox',
            'radio',
            'number',
            'date',
            'url',
            'email',
            'tel',
            'password',
            'hidden',
            'color',
            'file',
            'wp_media'
        ];

        foreach ($types as $type) {
            $this->input_type_html[$type] = fn($id, $data) => $this->render_default_field($type, $id, $data);
        }
    }

    /**
     * Render default field HTML.
     *
     * @param string $type Field type
     * @param string $id Field ID
     * @param array $data Field data
     * @return void
     */
    private function render_default_field(string $type, string $id, array $data): void
    {
        if ($type === 'wp_media') {
            echo ViewLoader::get('wp-media-field', [
                'id' => $id,
                'data' => $data,
                'metabox' => $this,
                'attributes' => $this->attributes_to_string($data['attributes'] ?? []),
            ], self::INPUT_VIEW_BASE);
        } else {
            echo ViewLoader::get('field', [
                'type' => $type,
                'id' => $id,
                'data' => $data,
                'attributes' => $this->attributes_to_string($data['attributes'] ?? []),
                'metabox' => $this
            ], self::INPUT_VIEW_BASE);
        }
    }

    /**
     * Convert attributes array to HTML string.
     *
     * @param array $attrs Attributes array
     * @return string HTML attributes string
     */

    function attributes_to_string(array $attrs): string
    {
        $result = [];
        foreach ($attrs as $key => $value) {
            if (is_bool($value)) {
                if ($value) $result[] = esc_attr($key);
            } elseif (is_array($value)) {
                // Handle array values (like style arrays)
                if ($key === 'style') {
                    $style_string = '';
                    foreach ($value as $prop => $val) {
                        $style_string .= esc_attr($prop) . ':' . esc_attr($val) . ';';
                    }
                    $result[] = 'style="' . $style_string . '"';
                } else {
                    $result[] = esc_attr($key) . '="' . esc_attr(implode(' ', $value)) . '"';
                }
            } else {
                $result[] = esc_attr($key) . '="' . esc_attr($value) . '"';
            }
        }
        return implode(' ', $result);
    }

    /**
     * Setup default sanitizers.
     *
     * @return void
     */
    private function setup_default_sanitizers(): void
    {
        $this->custom_sanitizers = [
            'email' => 'sanitize_email',
            'url' => 'esc_url_raw',
            'textarea' => 'sanitize_textarea_field',
            'number' => 'absint'
        ];
    }

    /**
     * Clear field cache for a post.
     *
     * @param int $post_id Post ID
     * @return void
     */
    private function clear_field_cache(int $post_id): void
    {
        $cache_patterns = [
            "metabox_{$this->id}_meta_{$post_id}",
            "metabox_{$this->id}_field_*_{$post_id}"
        ];

        foreach ($cache_patterns as $pattern) {
            if (str_contains($pattern, '*')) {
                // Clear pattern-based cache (would need enhanced Cache utility)
                foreach ($this->fields as $field) {
                    $key = str_replace('*', $field['id'], $pattern);
                    Cache::delete($key, 'wptoolkit_metabox');
                }
            } else {
                Cache::delete($pattern, 'wptoolkit_metabox');
            }
        }
    }
}
