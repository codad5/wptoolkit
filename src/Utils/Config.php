<?php

/**
 * Configuration Helper
 *
 * @author Chibueze <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use InvalidArgumentException;
use JsonException;

/**
 * Immutable configuration management for applications.
 *
 * Provides a clean, object-based API for managing application configuration
 * with validation, type safety, and serialization support.
 */
final class Config
{
    /**
     * Configuration data storage.
     *
     * @var array<string, mixed>
     */
    private array $container;

    /**
     * Application slug (required for registry identification).
     */
    public readonly string $slug;

    /**
     * Constructor - creates a new configuration instance.
     *
     * @param array<string, mixed> $container Configuration data
     * @throws InvalidArgumentException If slug is missing or invalid
     */
    public function __construct(array $container)
    {
        $this->validateContainer($container);
        $this->container = $this->sanitizeContainer($container);
        $this->slug = $this->container['slug'];
    }

    /**
     * Static factory method for creating configuration instances.
     *
     * @param array<string, mixed> $container Configuration data
     * @return static New configuration instance
     * @throws InvalidArgumentException If configuration is invalid
     */
    public static function create(array $container): static
    {
        return new static($container);
    }

    /**
     * Static factory method with fluent interface for common configurations.
     *
     * @param string $slug Application slug (required)
     * @param string $name Application name
     * @param string $version Application version
     * @return static New configuration instance
     */
    public static function app(string $slug, string $name = '', string $version = '1.0.0'): static
    {
        $container = [
            'slug' => $slug,
            'name' => $name ?: ucfirst(str_replace(['-', '_'], ' ', $slug)),
            'version' => $version,
        ];

        return new static($container);
    }

    /**
     * Create configuration for a WordPress plugin.
     *
     * @param string $slug Plugin slug
     * @param string $file Main plugin file path
     * @param array<string, mixed> $additional Additional configuration
     * @return static New configuration instance
     */
    public static function plugin(string $slug, string $file, array $additional = []): static
    {
        $plugin_data = get_file_data($file, [
            'name' => 'Plugin Name',
            'version' => 'Version',
            'description' => 'Description',
            'author' => 'Author',
            'text_domain' => 'Text Domain',
        ]);

        $container = array_merge([
            'slug' => $slug,
            'file' => $file,
            'plugin_dir' => dirname($file),
            'plugin_url' => plugin_dir_url($file),
            'type' => 'plugin',
        ], $plugin_data, $additional);

        return new static($container);
    }

    /**
     * Create configuration for a WordPress theme.
     *
     * @param string $slug Theme slug
     * @param array<string, mixed> $additional Additional configuration
     * @return static New configuration instance
     */
    public static function theme(string $slug, array $additional = []): static
    {
        $theme = wp_get_theme();

        $container = array_merge([
            'slug' => $slug,
            'name' => $theme->get('Name'),
            'version' => $theme->get('Version'),
            'description' => $theme->get('Description'),
            'author' => $theme->get('Author'),
            'theme_dir' => get_template_directory(),
            'theme_url' => get_template_directory_uri(),
            'type' => 'theme',
        ], $additional);

        return new static($container);
    }

    /**
     * Get a configuration value.
     *
     * @param string $name Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->container[$name] ?? $default;
    }

    /**
     * Check if a configuration key exists.
     *
     * @param string $name Configuration key
     * @return bool Whether the key exists
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->container);
    }

    /**
     * Get all configuration data.
     *
     * @return array<string, mixed> All configuration data
     */
    public function all(): array
    {
        return $this->container;
    }

    /**
     * Get configuration keys.
     *
     * @return array<string> Array of configuration keys
     */
    public function keys(): array
    {
        return array_keys($this->container);
    }

    /**
     * Create a new configuration instance with additional data.
     *
     * @param array<string, mixed> $additional Additional configuration data
     * @param bool $overwrite Whether to overwrite existing keys
     * @return static New configuration instance
     */
    public function with(array $additional, bool $overwrite = true): static
    {
        if ($overwrite) {
            $new_container = array_merge($this->container, $additional);
        } else {
            $new_container = array_merge($additional, $this->container);
        }

        return new static($new_container);
    }

    /**
     * Create a new configuration instance without specified keys.
     *
     * @param array<string> $keys Keys to remove
     * @return static New configuration instance
     * @throws InvalidArgumentException If trying to remove slug
     */
    public function without(array $keys): static
    {
        // Check if trying to remove slug
        if (in_array('slug', $keys, true)) {
            throw new InvalidArgumentException('Cannot remove required "slug" key');
        }

        $new_container = $this->container;
        foreach ($keys as $key) {
            unset($new_container[$key]);
        }

        return new static($new_container);
    }

    /**
     * Create a new configuration instance with only specified keys.
     *
     * @param array<string> $keys Keys to keep
     * @return static New configuration instance
     * @throws InvalidArgumentException If slug is not included
     */
    public function only(array $keys): static
    {
        // Ensure slug is included
        if (!in_array('slug', $keys, true)) {
            throw new InvalidArgumentException('Required "slug" key must be included');
        }

        $new_container = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->container)) {
                $new_container[$key] = $this->container[$key];
            }
        }

        return new static($new_container);
    }

    /**
     * Get configuration formatted for specific use cases.
     *
     * @param string $format Format type ('array', 'json', 'env')
     * @param array<string> $exclude Keys to exclude
     * @return string|array<string, mixed> Formatted configuration
     * @throws InvalidArgumentException If format is unsupported
     */
    public function format(string $format, array $exclude = []): string|array
    {
        $data = $this->container;

        // Remove excluded keys
        foreach ($exclude as $key) {
            unset($data[$key]);
        }

        return match ($format) {
            'array' => $data,
            'json' => $this->toJson($data),
            'env' => $this->toEnvFormat($data),
            default => throw new InvalidArgumentException("Unsupported format: {$format}")
        };
    }

    /**
     * Export configuration as JSON.
     *
     * @param int $flags JSON encoding flags
     * @return string JSON representation
     * @throws JsonException If JSON encoding fails
     */
    public function toJson(int $flags = JSON_THROW_ON_ERROR): string
    {
        return json_encode($this->container, $flags);
    }

    /**
     * Create configuration from JSON.
     *
     * @param string $json JSON string
     * @return static New configuration instance
     * @throws JsonException If JSON is invalid
     * @throws InvalidArgumentException If resulting data is invalid
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new InvalidArgumentException('JSON must decode to an array');
        }

        return new static($data);
    }

    /**
     * Create configuration from environment variables.
     *
     * @param string $prefix Environment variable prefix
     * @param array<string, mixed> $defaults Default values
     * @return static New configuration instance
     */
    public static function fromEnv(string $prefix, array $defaults = []): static
    {
        $config = $defaults;
        $prefix = rtrim($prefix, '_') . '_';

        foreach ($_ENV as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $config_key = strtolower(substr($key, strlen($prefix)));
                $config[$config_key] = $value;
            }
        }

        return new static($config);
    }

    /**
     * Check if this is a development environment.
     *
     * @return bool Whether this is development environment
     */
    public function isDevelopment(): bool
    {
        return $this->get('environment') === 'development' ||
            $this->get('debug', false) === true ||
            (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * Check if this is a production environment.
     *
     * @return bool Whether this is production environment
     */
    public function isProduction(): bool
    {
        return $this->get('environment') === 'production' ||
            (!$this->isDevelopment() && !$this->isStaging());
    }

    /**
     * Check if this is a staging environment.
     *
     * @return bool Whether this is staging environment
     */
    public function isStaging(): bool
    {
        return $this->get('environment') === 'staging';
    }

    /**
     * Get the application URL based on configuration.
     *
     * @param string $path Optional path to append
     * @return string Application URL
     */
    public function url(string $path = ''): string
    {
        $base_url = '';

        if ($this->get('type') === 'plugin') {
            $base_url = $this->get('plugin_url', plugins_url());
        } elseif ($this->get('type') === 'theme') {
            $base_url = $this->get('theme_url', get_template_directory_uri());
        } else {
            $base_url = $this->get('base_url', home_url());
        }

        if (!empty($path)) {
            $base_url = rtrim($base_url, '/') . '/' . ltrim($path, '/');
        }

        return $base_url;
    }

    /**
     * Get the application directory path.
     *
     * @param string $path Optional path to append
     * @return string Application directory path
     */
    public function path(string $path = ''): string
    {
        $base_path = '';

        if ($this->get('type') === 'plugin') {
            $base_path = $this->get('plugin_dir');
        } elseif ($this->get('type') === 'theme') {
            $base_path = $this->get('theme_dir', get_template_directory());
        } else {
            $base_path = $this->get('base_path', ABSPATH);
        }

        if (!empty($path)) {
            $base_path = rtrim($base_path, '/') . '/' . ltrim($path, '/');
        }

        return $base_path;
    }

    /**
     * Magic method to prevent modification of configuration.
     *
     * @param string $name Property name
     * @param mixed $value Property value
     * @throws \Exception Always throws exception
     */
    public function __set(string $name, mixed $value): void
    {
        throw new \Exception('Configuration is immutable. Create a new instance with with() method.');
    }

    /**
     * Magic method to get configuration values.
     *
     * @param string $name Configuration key
     * @return mixed Configuration value
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Magic method to check if configuration key exists.
     *
     * @param string $name Configuration key
     * @return bool Whether the key exists
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }

    /**
     * Debug information for var_dump.
     *
     * @return array<string, mixed> Debug data
     */
    public function __debugInfo(): array
    {
        return [
            'slug' => $this->slug,
            'container' => $this->container,
        ];
    }

    /**
     * String representation of the configuration.
     *
     * @return string JSON representation
     */
    public function __toString(): string
    {
        try {
            return $this->toJson(JSON_PRETTY_PRINT);
        } catch (JsonException) {
            return "Config[slug={$this->slug}]";
        }
    }

    /**
     * Validate the configuration container.
     *
     * @param array<string, mixed> $container Configuration data
     * @return void
     * @throws InvalidArgumentException If validation fails
     */
    private function validateContainer(array $container): void
    {
        // Check if slug exists and is valid
        if (!isset($container['slug']) || !is_string($container['slug']) || empty(trim($container['slug']))) {
            throw new InvalidArgumentException('Configuration must have a valid "slug" string');
        }

        // Validate slug format
        $slug = trim($container['slug']);
        if (!preg_match('/^[a-z0-9_-]+$/i', $slug)) {
            throw new InvalidArgumentException('Slug must contain only letters, numbers, hyphens, and underscores');
        }
    }

    /**
     * Sanitize the configuration container.
     *
     * @param array<string, mixed> $container Configuration data
     * @return array<string, mixed> Sanitized configuration data
     */
    private function sanitizeContainer(array $container): array
    {
        // Sanitize slug
        $container['slug'] = sanitize_key($container['slug']);

        // Sanitize other common fields
        if (isset($container['name']) && is_string($container['name'])) {
            $container['name'] = sanitize_text_field($container['name']);
        }

        if (isset($container['version']) && is_string($container['version'])) {
            $container['version'] = sanitize_text_field($container['version']);
        }

        if (isset($container['description']) && is_string($container['description'])) {
            $container['description'] = sanitize_textarea_field($container['description']);
        }

        return $container;
    }

    /**
     * Convert data to environment variable format.
     *
     * @param array<string, mixed> $data Data to convert
     * @return string Environment variable format
     */
    private function toEnvFormat(array $data): string
    {
        $lines = [];
        $prefix = strtoupper($this->slug) . '_';

        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $env_key = $prefix . strtoupper($key);
                $env_value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
                $lines[] = "{$env_key}={$env_value}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Convert array data to JSON, handling the array parameter.
     *
     * @param array<string, mixed> $data Data to convert
     * @return string JSON representation
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
