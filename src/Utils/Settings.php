<?php

/**
 * Settings Helper
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use InvalidArgumentException;

/**
 * Settings Helper class for managing plugin settings and options.
 *
 * Provides a clean API for WordPress settings management with validation,
 * sanitization, and automatic WordPress Settings API integration.
 * Now fully object-based with dependency injection support.
 */
class Settings
{
    /**
     * Settings configuration array.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $settings = [];

    /**
     * Option prefix for all settings.
     */
    protected string $option_prefix;

    /**
     * Settings groups for organizing options.
     *
     * @var array<string>
     */
    protected array $groups = [];

    /**
     * Default values cache.
     *
     * @var array<string, mixed>
     */
    protected array $defaults_cache = [];

    /**
     * Application slug for text domain and identification.
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
     * Custom notification callback.
     *
     * @var callable|null
     */
    protected $notification_callback = null;

    /**
     * Whether hooks have been registered.
     */
    protected bool $hooks_registered = false;

    /**
     * Constructor for creating a new Settings instance.
     *
     * @param array<string, array<string, mixed>> $settings Settings configuration
     * @param Config|string|null $config_or_slug Config instance, app slug, or null
     * @param string|null $option_prefix Custom option prefix (optional)
     * @param callable|null $notification_callback Custom notification handler
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(
        array $settings = [],
        Config|string|null $config_or_slug = null,
        ?string $option_prefix = null,
        ?callable $notification_callback = null
    ) {
        $this->parseConfigOrSlug($config_or_slug);
        $this->settings = $settings;
        $this->option_prefix = $option_prefix ?? $this->app_slug . '_';
        $this->notification_callback = $notification_callback;

        $this->registerHooks();
    }

    /**
     * Static factory method for creating Settings instances.
     *
     * @param array<string, array<string, mixed>> $settings Settings configuration
     * @param Config|string|null $config_or_slug Config instance or app slug
     * @param string|null $option_prefix Custom option prefix
     * @param callable|null $notification_callback Custom notification handler
     * @return static New Settings instance
     */
    public static function create(
        array $settings = [],
        Config|string|null $config_or_slug = null,
        ?string $option_prefix = null,
        ?callable $notification_callback = null
    ): static {
        return new static($settings, $config_or_slug, $option_prefix, $notification_callback);
    }

    /**
     * Add a setting configuration.
     *
     * @param string $key Setting key
     * @param array<string, mixed> $config Setting configuration
     * @return bool Success status
     */
    public function addSetting(string $key, array $config): bool
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

        $this->settings[$key] = array_merge($defaults, $config);

        // Clear defaults cache when adding new settings
        $this->defaults_cache = [];

        // Re-register hooks if needed
        if ($this->hooks_registered) {
            $this->registerSettingsWithWordPress();
        }

        return true;
    }

    /**
     * Add multiple settings at once.
     *
     * @param array<string, array<string, mixed>> $settings Settings to add
     * @return static For method chaining
     */
    public function addSettings(array $settings): static
    {
        foreach ($settings as $key => $config) {
            $this->addSetting($key, $config);
        }
        return $this;
    }

    /**
     * Get a setting value.
     *
     * @param string $key Setting key
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed Setting value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $option_name = $this->getOptionName($key);

        // Use configured default if no default provided
        if ($default === null) {
            $default = $this->getDefault($key);
        }

        $value = get_option($option_name, $default);

        // Apply any post-processing
        return $this->processSettingValue($key, $value);
    }

    /**
     * Set a setting value.
     *
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function set(string $key, mixed $value): bool
    {
        $option_name = $this->getOptionName($key);

        // Sanitize the value
        $sanitized_value = $this->sanitizeSettingValue($key, $value);

        // Validate the value
        if (!$this->validateSettingValue($key, $sanitized_value)) {
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
    public function delete(string $key): bool
    {
        $option_name = $this->getOptionName($key);
        return delete_option($option_name);
    }

    /**
     * Get all settings as an array.
     *
     * @param string|null $group Optional group to filter by
     * @return array<string, mixed> Settings array
     */
    public function getAll(?string $group = null): array
    {
        $settings = [];

        foreach ($this->settings as $key => $config) {
            if ($group && ($config['group'] ?? 'general') !== $group) {
                continue;
            }

            $settings[$key] = $this->get($key);
        }

        return $settings;
    }

    /**
     * Reset a setting to its default value.
     *
     * @param string $key Setting key
     * @return bool Success status
     */
    public function reset(string $key): bool
    {
        $default = $this->getDefault($key);
        return $this->set($key, $default);
    }

    /**
     * Reset all settings to default values.
     *
     * @param string|null $group Optional group to filter by
     * @return bool Success status
     */
    public function resetAll(?string $group = null): bool
    {
        $success = true;

        foreach ($this->settings as $key => $config) {
            if ($group && ($config['group'] ?? 'general') !== $group) {
                continue;
            }

            if (!$this->reset($key)) {
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
    public function getSettingConfig(string $key): ?array
    {
        return $this->settings[$key] ?? null;
    }

    /**
     * Get all setting configurations.
     *
     * @param string|null $group Optional group to filter by
     * @return array<string, array<string, mixed>> Settings configurations
     */
    public function getSettingsConfig(?string $group = null): array
    {
        if ($group === null) {
            return $this->settings;
        }

        return array_filter($this->settings, function ($config) use ($group) {
            return ($config['group'] ?? 'general') === $group;
        });
    }

    /**
     * Get all registered groups.
     *
     * @return array<string> Group names
     */
    public function getGroups(): array
    {
        return array_unique($this->groups);
    }

    /**
     * Import settings from an array.
     *
     * @param array<string, mixed> $data Settings data
     * @param bool $validate Whether to validate imported data
     * @return array<string, bool> Results keyed by setting key
     */
    public function import(array $data, bool $validate = true): array
    {
        $results = [];

        foreach ($data as $key => $value) {
            if (!isset($this->settings[$key])) {
                $results[$key] = false;
                continue;
            }

            if ($validate) {
                $results[$key] = $this->set($key, $value);
            } else {
                $option_name = $this->getOptionName($key);
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
    public function export(?string $group = null): array
    {
        return $this->getAll($group);
    }

    /**
     * Clear various caches related to settings.
     *
     * @return bool Success status
     */
    public function clearCaches(): bool
    {
        // Clear WordPress object cache
        wp_cache_flush();

        // Clear any transients used by the plugin
        $transient_keys = [
            $this->app_slug . '_cache',
            $this->app_slug . '_api_cache',
            $this->app_slug . '_settings_cache',
        ];

        foreach ($transient_keys as $key) {
            delete_transient($key);
        }

        // Clear our internal cache
        $this->defaults_cache = [];

        return true;
    }

    /**
     * Render a settings field.
     *
     * @param string $key Setting key
     * @param array<string, mixed> $args Additional arguments
     * @return string HTML output
     */
    public function renderField(string $key, array $args = []): string
    {
        $config = $this->getSettingConfig($key);
        if (!$config) {
            return '';
        }

        $value = $this->get($key);
        $option_name = $this->getOptionName($key);
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
                echo $this->renderTextField($option_name, $value, $type, $attributes, $config);
                break;

            case 'number':
                echo $this->renderNumberField($option_name, $value, $attributes, $config);
                break;

            case 'textarea':
                echo $this->renderTextareaField($option_name, $value, $attributes, $config);
                break;

            case 'select':
                echo $this->renderSelectField($option_name, $value, $attributes, $config);
                break;

            case 'checkbox':
                echo $this->renderCheckboxField($option_name, $value, $attributes, $config);
                break;

            case 'radio':
                echo $this->renderRadioField($option_name, $value, $attributes, $config);
                break;

            default:
                echo $this->renderTextField($option_name, $value, 'text', $attributes, $config);
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
     * Send a success notification.
     *
     * @param string $message Notification message
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public function sendSuccessNotification(string $message, array $args = []): bool
    {
        return $this->sendNotification($message, 'success', $args);
    }

    /**
     * Send an error notification.
     *
     * @param string $message Notification message
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public function sendErrorNotification(string $message, array $args = []): bool
    {
        return $this->sendNotification($message, 'error', $args);
    }

    /**
     * Send a warning notification.
     *
     * @param string $message Notification message
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public function sendWarningNotification(string $message, array $args = []): bool
    {
        return $this->sendNotification($message, 'warning', $args);
    }

    /**
     * Send an info notification.
     *
     * @param string $message Notification message
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public function sendInfoNotification(string $message, array $args = []): bool
    {
        return $this->sendNotification($message, 'info', $args);
    }

    /**
     * Set custom notification callback.
     *
     * @param callable $callback Notification callback function
     * @return static For method chaining
     */
    public function setNotificationCallback(callable $callback): static
    {
        $this->notification_callback = $callback;
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
     * Get the option prefix.
     *
     * @return string Option prefix
     */
    public function getOptionPrefix(): string
    {
        return $this->option_prefix;
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

        add_action('admin_init', [$this, 'registerSettingsWithWordPress']);
        add_action('admin_post_' . $this->getClearCacheAction(), [$this, 'handleClearCache']);

        $this->hooks_registered = true;
    }

    /**
     * Register settings with WordPress Settings API.
     *
     * @return void
     */
    public function registerSettingsWithWordPress(): void
    {
        foreach ($this->settings as $key => $setting) {
            $option_name = $this->getOptionName($key);
            $group = $setting['group'] ?? 'general';

            // Register the setting
            register_setting($group, $option_name, [
                'type' => $setting['type'] ?? 'string',
                'description' => $setting['description'] ?? '',
                'sanitize_callback' => $setting['sanitize_callback'] ?? [$this, 'sanitizeSetting'],
                'default' => $setting['default'] ?? '',
            ]);

            // Track groups
            if (!in_array($group, $this->groups, true)) {
                $this->groups[] = $group;
            }
        }
    }

    /**
     * Handle cache clearing via admin_post action.
     *
     * @return void
     */
    public function handleClearCache(): void
    {
        // Verify nonce
        $nonce = sanitize_text_field($_POST['_wpnonce'] ?? '');
        if (!wp_verify_nonce($nonce, $this->getClearCacheAction())) {
            wp_die(esc_html__('Invalid security token', $this->text_domain));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions', $this->text_domain));
        }

        // Clear relevant caches
        $this->clearCaches();

        // Send success notification
        $this->sendSuccessNotification(
            __('Settings cache cleared successfully', $this->text_domain)
        );

        // Redirect back
        $redirect_url = wp_get_referer() ?: admin_url();
        wp_safe_redirect(esc_url_raw($redirect_url));
        exit;
    }

    /**
     * Parse config or slug parameter.
     *
     * @param Config|string|null $config_or_slug Config instance, app slug, or null
     * @return void
     * @throws InvalidArgumentException If parameter is invalid
     */
    protected function parseConfigOrSlug(Config|string|null $config_or_slug): void
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
            // Default values
            $this->config = null;
            $this->app_slug = 'wp_plugin';
            $this->text_domain = 'wp_plugin';
        }

        if (empty($this->app_slug)) {
            throw new InvalidArgumentException('App slug cannot be empty');
        }
    }

    /**
     * Send a notification using the configured method.
     *
     * @param string $message Notification message
     * @param string $type Notification type
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    protected function sendNotification(string $message, string $type = 'info', array $args = []): bool
    {
        // Use custom callback if provided
        if ($this->notification_callback !== null) {
            try {
                call_user_func($this->notification_callback, $message, $type, $args);
                return true;
            } catch (\Throwable $e) {
                // Fall back to default if custom callback fails
                error_log("Settings notification callback failed: " . $e->getMessage());
            }
        }

        // Try to use the default Notification class
        if (class_exists(Notification::class)) {
            try {
                $notification = Notification::create($this->config ?: $this->app_slug);
                return match ($type) {
                    'success' => $notification->success($message, $args['pages'] ?? 'current', $args['expiration'] ?? null),
                    'error' => $notification->error($message, $args['pages'] ?? 'current', $args['expiration'] ?? null),
                    'warning' => $notification->warning($message, $args['pages'] ?? 'current', $args['expiration'] ?? null),
                    'info' => $notification->info($message, $args['pages'] ?? 'current', $args['expiration'] ?? null),
                    default => $notification->info($message, $args['pages'] ?? 'current', $args['expiration'] ?? null)
                };
            } catch (\Throwable $e) {
                error_log("Default notification failed: " . $e->getMessage());
            }
        }

        // Last resort: add admin notice hook
        add_action('admin_notices', function () use ($message, $type) {
            $class = match ($type) {
                'success' => 'notice-success',
                'error' => 'notice-error',
                'warning' => 'notice-warning',
                default => 'notice-info'
            };

            printf(
                '<div class="notice %s is-dismissible"><p>%s</p></div>',
                esc_attr($class),
                esc_html($message)
            );
        });

        return true;
    }

    /**
     * Get the clear cache action name.
     *
     * @return string Action name
     */
    protected function getClearCacheAction(): string
    {
        return $this->app_slug . '_clear_cache';
    }

    /**
     * Get the full option name for a setting.
     *
     * @param string $key Setting key
     * @return string Full option name
     */
    protected function getOptionName(string $key): string
    {
        return $this->option_prefix . sanitize_key($key);
    }

    /**
     * Get the default value for a setting.
     *
     * @param string $key Setting key
     * @return mixed Default value
     */
    protected function getDefault(string $key): mixed
    {
        if (!isset($this->defaults_cache[$key])) {
            $config = $this->settings[$key] ?? [];
            $this->defaults_cache[$key] = $config['default'] ?? '';
        }

        return $this->defaults_cache[$key];
    }

    /**
     * Process setting value after retrieval.
     *
     * @param string $key Setting key
     * @param mixed $value Raw value
     * @return mixed Processed value
     */
    protected function processSettingValue(string $key, mixed $value): mixed
    {
        $config = $this->settings[$key] ?? [];
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
     * Sanitize a setting value (callback for WordPress).
     *
     * @param mixed $value Value to sanitize
     * @param string $option Option name
     * @return mixed Sanitized value
     */
    public function sanitizeSetting(mixed $value, string $option = ''): mixed
    {
        // Extract key from option name
        $key = str_replace($this->option_prefix, '', $option);
        return $this->sanitizeSettingValue($key, $value);
    }

    /**
     * Sanitize a setting value by key.
     *
     * @param string $key Setting key
     * @param mixed $value Value to sanitize
     * @return mixed Sanitized value
     */
    protected function sanitizeSettingValue(string $key, mixed $value): mixed
    {
        $config = $this->settings[$key] ?? [];

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
    protected function validateSettingValue(string $key, mixed $value): bool
    {
        $config = $this->settings[$key] ?? [];

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

    // Field rendering methods (keeping them concise)

    protected function renderTextField(string $name, mixed $value, string $type, array $attributes, array $config): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'type' => $type,
            'name' => $name,
            'id' => $name,
            'value' => $value,
            'class' => 'regular-text',
            'placeholder' => $config['placeholder'] ?? '',
        ], $attributes));

        return sprintf('<input %s />', $attrs);
    }

    protected function renderNumberField(string $name, mixed $value, array $attributes, array $config): string
    {
        $attrs = $this->buildAttributes(array_merge([
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

    protected function renderTextareaField(string $name, mixed $value, array $attributes, array $config): string
    {
        $attrs = $this->buildAttributes(array_merge([
            'name' => $name,
            'id' => $name,
            'class' => 'large-text',
            'rows' => $config['rows'] ?? 5,
            'cols' => $config['cols'] ?? 50,
            'placeholder' => $config['placeholder'] ?? '',
        ], $attributes));

        return sprintf('<textarea %s>%s</textarea>', $attrs, esc_textarea($value));
    }

    protected function renderSelectField(string $name, mixed $value, array $attributes, array $config): string
    {
        $choices = $config['choices'] ?? [];

        $attrs = $this->buildAttributes(array_merge([
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

    protected function renderCheckboxField(string $name, mixed $value, array $attributes, array $config): string
    {
        $attrs = $this->buildAttributes(array_merge([
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

    protected function renderRadioField(string $name, mixed $value, array $attributes, array $config): string
    {
        $choices = $config['choices'] ?? [];
        $output = '';

        foreach ($choices as $option_value => $option_label) {
            $attrs = $this->buildAttributes(array_merge([
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

    protected function buildAttributes(array $attributes): string
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
