<?php

/**
 * Settings Helper
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;


/**
 * Settings Helper class for managing plugin settings and options.
 *
 * Provides a clean API for WordPress settings management with validation,
 * sanitization, and automatic WordPress Settings API integration.
 */
class Settings
{
    /**
     * Settings configuration array.
     */
    protected static array $settings = [];

    /**
     * Option prefix for all settings.
     */
    protected static string $option_prefix = '';

    /**
     * Settings groups for organizing options.
     */
    protected static array $groups = [];

    /**
     * Default values cache.
     */
    protected static array $defaults_cache = [];

    /**
     * Initialize the settings system.
     *
     * @param array<string, array<string, mixed>> $settings Settings configuration
     * @param string|null $option_prefix Custom option prefix
     * @return bool Success status
     */
    public static function init(array $settings = [], ?string $option_prefix = null): bool
    {
        self::$settings = $settings;
        self::$option_prefix = $option_prefix ?? (Config::get('slug') ?? 'wp_plugin') . '_';

        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_post_' . self::get_clear_cache_action(), [self::class, 'handle_clear_cache']);

        return true;
    }

    /**
     * Add a setting configuration.
     *
     * @param string $key Setting key
     * @param array<string, mixed> $config Setting configuration
     * @return bool Success status
     */
    public static function add_setting(string $key, array $config): bool
    {
        $defaults = [
            'type' => 'text',
            'label' => ucfirst(str_replace('_', ' ', $key)),
            'description' => '',
            'default' => '',
            'group' => 'general',
            'sanitize_callback' => null,
            'validate_callback' => null,
            'choices' => [],
            'attributes' => [],
        ];

        self::$settings[$key] = array_merge($defaults, $config);

        // Clear defaults cache when adding new settings
        self::$defaults_cache = [];

        return true;
    }

    /**
     * Add multiple settings at once.
     *
     * @param array<string, array<string, mixed>> $settings Settings to add
     * @return bool Success status
     */
    public static function add_settings(array $settings): bool
    {
        foreach ($settings as $key => $config) {
            self::add_setting($key, $config);
        }
        return true;
    }

    /**
     * Register settings with WordPress Settings API.
     *
     * @return void
     */
    public static function register_settings(): void
    {
        foreach (self::$settings as $key => $setting) {
            $option_name = self::get_option_name($key);
            $group = $setting['group'] ?? 'general';

            // Register the setting
            register_setting($group, $option_name, [
                'type' => $setting['type'] ?? 'string',
                'description' => $setting['description'] ?? '',
                'sanitize_callback' => $setting['sanitize_callback'] ?? [self::class, 'sanitize_setting'],
                'default' => $setting['default'] ?? '',
            ]);

            // Track groups
            if (!in_array($group, self::$groups, true)) {
                self::$groups[] = $group;
            }
        }
    }

    /**
     * Get a setting value.
     *
     * @param string $key Setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed Setting value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $option_name = self::get_option_name($key);

        // Use configured default if no default provided
        if ($default === null) {
            $default = self::get_default($key);
        }

        $value = get_option($option_name, $default);

        // Apply any post-processing
        return self::process_setting_value($key, $value);
    }

    /**
     * Set a setting value.
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public static function set(string $key, mixed $value): bool
    {
        $option_name = self::get_option_name($key);

        // Sanitize the value
        $sanitized_value = self::sanitize_setting_value($key, $value);

        // Validate the value
        if (!self::validate_setting_value($key, $sanitized_value)) {
            return false;
        }

        return update_option($option_name, $sanitized_value);
    }

    /**
     * Delete a setting.
     *
     * @param string $key Setting key
     * @return bool Success status
     */
    public static function delete(string $key): bool
    {
        $option_name = self::get_option_name($key);
        return delete_option($option_name);
    }

    /**
     * Get all settings as an array.
     *
     * @param string|null $group Optional group to filter by
     * @return array<string, mixed> Settings array
     */
    public static function get_all(?string $group = null): array
    {
        $settings = [];

        foreach (self::$settings as $key => $config) {
            if ($group && ($config['group'] ?? 'general') !== $group) {
                continue;
            }

            $settings[$key] = self::get($key);
        }

        return $settings;
    }

    /**
     * Reset a setting to its default value.
     *
     * @param string $key Setting key
     * @return bool Success status
     */
    public static function reset(string $key): bool
    {
        $default = self::get_default($key);
        return self::set($key, $default);
    }

    /**
     * Reset all settings to default values.
     *
     * @param string|null $group Optional group to filter by
     * @return bool Success status
     */
    public static function reset_all(?string $group = null): bool
    {
        $success = true;

        foreach (self::$settings as $key => $config) {
            if ($group && ($config['group'] ?? 'general') !== $group) {
                continue;
            }

            if (!self::reset($key)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Get setting configuration.
     *
     * @param string $key Setting key
     * @return array<string, mixed>|null Setting configuration or null if not found
     */
    public static function get_setting_config(string $key): ?array
    {
        return self::$settings[$key] ?? null;
    }

    /**
     * Get all setting configurations.
     *
     * @param string|null $group Optional group to filter by
     * @return array<string, array<string, mixed>> Settings configurations
     */
    public static function get_settings_config(?string $group = null): array
    {
        if ($group === null) {
            return self::$settings;
        }

        return array_filter(self::$settings, function ($config) use ($group) {
            return ($config['group'] ?? 'general') === $group;
        });
    }

    /**
     * Get all registered groups.
     *
     * @return array<string> Group names
     */
    public static function get_groups(): array
    {
        return array_unique(self::$groups);
    }

    /**
     * Import settings from an array.
     *
     * @param array<string, mixed> $data Settings data
     * @param bool $validate Whether to validate imported data
     * @return array<string, bool> Results keyed by setting key
     */
    public static function import(array $data, bool $validate = true): array
    {
        $results = [];

        foreach ($data as $key => $value) {
            if (!isset(self::$settings[$key])) {
                $results[$key] = false;
                continue;
            }

            if ($validate) {
                $results[$key] = self::set($key, $value);
            } else {
                $option_name = self::get_option_name($key);
                $results[$key] = update_option($option_name, $value);
            }
        }

        return $results;
    }

    /**
     * Export settings as an array.
     *
     * @param string|null $group Optional group to filter by
     * @return array<string, mixed> Settings data
     */
    public static function export(?string $group = null): array
    {
        return self::get_all($group);
    }

    /**
     * Handle cache clearing via admin_post action.
     *
     * @return void
     */
    public static function handle_clear_cache(): void
    {
        // Verify nonce
        $nonce = sanitize_text_field($_POST['_wpnonce'] ?? '');
        if (!wp_verify_nonce($nonce, self::get_clear_cache_action())) {
            wp_die(esc_html__('Invalid security token', 'textdomain'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', 'textdomain'));
        }

        // Clear relevant caches
        self::clear_caches();

        // Add success notification if Notification helper is available
        if (class_exists(Notification::class)) {
            Notification::success(__('Settings cache cleared successfully', 'textdomain'));
        }

        // Redirect back
        $redirect_url = wp_get_referer() ?: admin_url();
        wp_safe_redirect(esc_url_raw($redirect_url));
        exit;
    }

    /**
     * Clear various caches related to settings.
     *
     * @return bool Success status
     */
    public static function clear_caches(): bool
    {
        // Clear WordPress object cache
        wp_cache_flush();

        // Clear any transients used by the plugin
        $plugin_slug = Config::get('slug') ?? 'wp_plugin';
        $transient_keys = [
            $plugin_slug . '_cache',
            $plugin_slug . '_api_cache',
            $plugin_slug . '_settings_cache',
        ];

        foreach ($transient_keys as $key) {
            delete_transient($key);
        }

        // Clear our internal cache
        self::$defaults_cache = [];

        return true;
    }

    /**
     * Render a settings field.
     *
     * @param string $key Setting key
     * @param array<string, mixed> $args Additional arguments
     * @return string HTML output
     */
    public static function render_field(string $key, array $args = []): string
    {
        $config = self::get_setting_config($key);
        if (!$config) {
            return '';
        }

        $value = self::get($key);
        $option_name = self::get_option_name($key);
        $type = $config['type'] ?? 'text';

        $attributes = array_merge(
            $config['attributes'] ?? [],
            $args['attributes'] ?? []
        );

        ob_start();

        switch ($type) {
            case 'text':
            case 'email':
            case 'url':
            case 'password':
                echo self::render_text_field($option_name, $value, $type, $attributes, $config);
                break;

            case 'number':
                echo self::render_number_field($option_name, $value, $attributes, $config);
                break;

            case 'textarea':
                echo self::render_textarea_field($option_name, $value, $attributes, $config);
                break;

            case 'select':
                echo self::render_select_field($option_name, $value, $attributes, $config);
                break;

            case 'checkbox':
                echo self::render_checkbox_field($option_name, $value, $attributes, $config);
                break;

            case 'radio':
                echo self::render_radio_field($option_name, $value, $attributes, $config);
                break;

            default:
                echo self::render_text_field($option_name, $value, 'text', $attributes, $config);
        }

        if (!empty($config['description'])) {
            printf(
                '<p class="description">%s</p>',
                wp_kses_post($config['description'])
            );
        }

        return ob_get_clean();
    }

    /**
     * Get the clear cache action name.
     *
     * @return string Action name
     */
    protected static function get_clear_cache_action(): string
    {
        return (Config::get('slug') ?? 'wp_plugin') . '_clear_cache';
    }

    /**
     * Get the full option name for a setting.
     *
     * @param string $key Setting key
     * @return string Full option name
     */
    protected static function get_option_name(string $key): string
    {
        return self::$option_prefix . sanitize_key($key);
    }

    /**
     * Get the default value for a setting.
     *
     * @param string $key Setting key
     * @return mixed Default value
     */
    protected static function get_default(string $key): mixed
    {
        if (!isset(self::$defaults_cache[$key])) {
            $config = self::$settings[$key] ?? [];
            self::$defaults_cache[$key] = $config['default'] ?? '';
        }

        return self::$defaults_cache[$key];
    }

    /**
     * Process setting value after retrieval.
     *
     * @param string $key Setting key
     * @param mixed $value Raw value
     * @return mixed Processed value
     */
    protected static function process_setting_value(string $key, mixed $value): mixed
    {
        $config = self::$settings[$key] ?? [];
        $type = $config['type'] ?? 'text';

        switch ($type) {
            case 'checkbox':
                return (bool) $value;

            case 'number':
                return is_numeric($value) ? (int) $value : 0;

            default:
                return $value;
        }
    }

    /**
     * Sanitize a setting value.
     *
     * @param mixed $value Value to sanitize
     * @param string $option Option name
     * @return mixed Sanitized value
     */
    public static function sanitize_setting(mixed $value, string $option = ''): mixed
    {
        // Extract key from option name
        $key = str_replace(self::$option_prefix, '', $option);
        return self::sanitize_setting_value($key, $value);
    }

    /**
     * Sanitize a setting value by key.
     *
     * @param string $key Setting key
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    protected static function sanitize_setting_value(string $key, mixed $value): mixed
    {
        $config = self::$settings[$key] ?? [];

        // Use custom sanitize callback if provided
        if (isset($config['sanitize_callback']) && is_callable($config['sanitize_callback'])) {
            return call_user_func($config['sanitize_callback'], $value);
        }

        $type = $config['type'] ?? 'text';

        switch ($type) {
            case 'text':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'email':
                return sanitize_email($value);

            case 'url':
                return esc_url_raw($value);

            case 'number':
                return absint($value);

            case 'checkbox':
                return (bool) $value;

            case 'select':
            case 'radio':
                $choices = $config['choices'] ?? [];
                return in_array($value, array_keys($choices), true) ? $value : '';

            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Validate a setting value.
     *
     * @param string $key Setting key
     * @param mixed $value Value to validate
     * @return bool Whether value is valid
     */
    protected static function validate_setting_value(string $key, mixed $value): bool
    {
        $config = self::$settings[$key] ?? [];

        // Use custom validate callback if provided
        if (isset($config['validate_callback']) && is_callable($config['validate_callback'])) {
            return (bool) call_user_func($config['validate_callback'], $value);
        }

        $type = $config['type'] ?? 'text';

        switch ($type) {
            case 'email':
                return empty($value) || is_email($value);

            case 'url':
                return empty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'number':
                return is_numeric($value);

            case 'select':
            case 'radio':
                $choices = $config['choices'] ?? [];
                return empty($value) || array_key_exists($value, $choices);

            default:
                return true; // Basic types are always valid after sanitization
        }
    }

    /**
     * Render a text input field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param string $type Input type
     * @param array<string, mixed> $attributes HTML attributes
     * @param array<string, mixed> $config Field configuration
     * @return string HTML output
     */
    protected static function render_text_field(string $name, mixed $value, string $type, array $attributes, array $config): string
    {
        $attrs = self::build_attributes(array_merge([
            'type' => $type,
            'name' => $name,
            'id' => $name,
            'value' => $value,
            'class' => 'regular-text',
            'placeholder' => $config['placeholder'] ?? '',
        ], $attributes));

        return sprintf('<input %s />', $attrs);
    }

    /**
     * Render a number input field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $attributes HTML attributes
     * @param array<string, mixed> $config Field configuration
     * @return string HTML output
     */
    protected static function render_number_field(string $name, mixed $value, array $attributes, array $config): string
    {
        $attrs = self::build_attributes(array_merge([
            'type' => 'number',
            'name' => $name,
            'id' => $name,
            'value' => $value,
            'class' => 'small-text',
            'min' => $config['min'] ?? null,
            'max' => $config['max'] ?? null,
            'step' => $config['step'] ?? null,
        ], $attributes));

        return sprintf('<input %s />', $attrs);
    }

    /**
     * Render a textarea field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $attributes HTML attributes
     * @param array<string, mixed> $config Field configuration
     * @return string HTML output
     */
    protected static function render_textarea_field(string $name, mixed $value, array $attributes, array $config): string
    {
        $attrs = self::build_attributes(array_merge([
            'name' => $name,
            'id' => $name,
            'class' => 'large-text',
            'rows' => $config['rows'] ?? 5,
            'cols' => $config['cols'] ?? 50,
            'placeholder' => $config['placeholder'] ?? '',
        ], $attributes));

        return sprintf('<textarea %s>%s</textarea>', $attrs, esc_textarea($value));
    }

    /**
     * Render a select field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $attributes HTML attributes
     * @param array<string, mixed> $config Field configuration
     * @return string HTML output
     */
    protected static function render_select_field(string $name, mixed $value, array $attributes, array $config): string
    {
        $choices = $config['choices'] ?? [];

        $attrs = self::build_attributes(array_merge([
            'name' => $name,
            'id' => $name,
        ], $attributes));

        $options = '';
        foreach ($choices as $option_value => $option_label) {
            $selected = selected($value, $option_value, false);
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                $selected,
                esc_html($option_label)
            );
        }

        return sprintf('<select %s>%s</select>', $attrs, $options);
    }

    /**
     * Render a checkbox field.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $attributes HTML attributes
     * @param array<string, mixed> $config Field configuration
     * @return string HTML output
     */
    protected static function render_checkbox_field(string $name, mixed $value, array $attributes, array $config): string
    {
        $attrs = self::build_attributes(array_merge([
            'type' => 'checkbox',
            'name' => $name,
            'id' => $name,
            'value' => '1',
        ], $attributes));

        $checked = checked($value, true, false);
        $label = $config['label'] ?? '';

        return sprintf(
            '<label><input %s %s /> %s</label>',
            $attrs,
            $checked,
            esc_html($label)
        );
    }

    /**
     * Render radio buttons.
     *
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $attributes HTML attributes
     * @param array<string, mixed> $config Field configuration
     * @return string HTML output
     */
    protected static function render_radio_field(string $name, mixed $value, array $attributes, array $config): string
    {
        $choices = $config['choices'] ?? [];
        $output = '';

        foreach ($choices as $option_value => $option_label) {
            $attrs = self::build_attributes(array_merge([
                'type' => 'radio',
                'name' => $name,
                'id' => $name . '_' . $option_value,
                'value' => $option_value,
            ], $attributes));

            $checked = checked($value, $option_value, false);

            $output .= sprintf(
                '<label><input %s %s /> %s</label><br>',
                $attrs,
                $checked,
                esc_html($option_label)
            );
        }

        return $output;
    }

    /**
     * Build HTML attributes string.
     *
     * @param array<string, mixed> $attributes Attributes array
     * @return string HTML attributes string
     */
    protected static function build_attributes(array $attributes): string
    {
        $attrs = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $attrs[] = esc_attr($name);
            } else {
                $attrs[] = sprintf('%s="%s"', esc_attr($name), esc_attr($value));
            }
        }

        return implode(' ', $attrs);
    }
}
