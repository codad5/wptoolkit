<?php

/**
 * Service Registry
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit;

use InvalidArgumentException;
use RuntimeException;

/**
 * Service Registry for managing application services and dependencies.
 *
 * Provides a centralized container for storing and retrieving service instances
 * across multiple applications with support for dependency injection patterns.
 */
final class Registry
{
    /**
     * Storage for all application services.
     * Structure: [app_slug => [service_name => service_instance]]
     *
     * @var array<string, array<string, object>>
     */
    private static array $services = [];

    /**
     * Storage for application configurations.
     * Structure: [app_slug => Config]
     *
     * @var array<string, Config>
     */
    private static array $configs = [];

    /**
     * Registered service factories for lazy loading.
     * Structure: [app_slug => [service_name => callable]]
     *
     * @var array<string, array<string, callable>>
     */
    private static array $factories = [];

    /**
     * Service aliases for easier access.
     * Structure: [app_slug => [alias => service_name]]
     *
     * @var array<string, array<string, string>>
     */
    private static array $aliases = [];

    /**
     * Prevent instantiation - this is a static registry.
     */
    private function __construct() {}

    /**
     * Register an application with its config and initial services.
     *
     * @param Config $config Application configuration
     * @param array<string, object> $services Initial services to register
     * @return bool Success status
     */
    public static function registerApp(Config $config, array $services = []): bool
    {
        $app_slug = $config->slug;

        // Store the config
        self::$configs[$app_slug] = $config;

        // Initialize services array for this app
        if (!isset(self::$services[$app_slug])) {
            self::$services[$app_slug] = [];
        }

        // Add initial services
        if (!empty($services)) {
            self::addMany($config, $services);
        }

        return true;
    }

    /**
     * Add a single service to an application.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param string $service_name Service identifier
     * @param object $service Service instance
     * @return bool Success status
     * @throws InvalidArgumentException If parameters are invalid
     */
    public static function add(Config|string $config_or_slug, string $service_name, object $service): bool
    {
        $app_slug = self::extractAppSlug($config_or_slug);

        if (empty($service_name)) {
            throw new InvalidArgumentException('Service name cannot be empty');
        }

        // Ensure app is registered
        self::ensureAppExists($app_slug);

        // Store the service
        self::$services[$app_slug][$service_name] = $service;

        return true;
    }

    /**
     * Add multiple services to an application.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param array<string, object> $services Services to add
     * @return bool Success status
     * @throws InvalidArgumentException If services array is invalid
     */
    public static function addMany(Config|string $config_or_slug, array $services): bool
    {
        $app_slug = self::extractAppSlug($config_or_slug);

        if (empty($services)) {
            return true; // Nothing to add
        }

        // Ensure app is registered
        self::ensureAppExists($app_slug);

        // Validate all services are objects
        foreach ($services as $name => $service) {
            if (!is_object($service)) {
                throw new InvalidArgumentException("Service '{$name}' must be an object");
            }
        }

        // Add all services
        foreach ($services as $service_name => $service) {
            self::$services[$app_slug][$service_name] = $service;
        }

        return true;
    }

    /**
     * Get a service from an application.
     *
     * @param string $app_slug Application slug
     * @param string $service_name Service identifier
     * @return object|null Service instance or null if not found
     * @throws RuntimeException If app doesn't exist
     */
    public static function get(string $app_slug, string $service_name): ?object
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered");
        }

        // Check for alias first
        if (isset(self::$aliases[$app_slug][$service_name])) {
            $service_name = self::$aliases[$app_slug][$service_name];
        }

        // Return existing service if available
        if (isset(self::$services[$app_slug][$service_name])) {
            return self::$services[$app_slug][$service_name];
        }

        // Try to create from factory
        if (isset(self::$factories[$app_slug][$service_name])) {
            $factory = self::$factories[$app_slug][$service_name];
            $service = $factory(self::$configs[$app_slug]);

            if (is_object($service)) {
                self::$services[$app_slug][$service_name] = $service;
                return $service;
            }
        }

        return null;
    }

    /**
     * Update/replace services in an application.
     *
     * @param string $app_slug Application slug
     * @param array<string, object> $services Services to update
     * @return bool Success status
     * @throws RuntimeException If app doesn't exist
     */
    public static function update(string $app_slug, array $services): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered");
        }

        // Validate all services are objects
        foreach ($services as $name => $service) {
            if (!is_object($service)) {
                throw new InvalidArgumentException("Service '{$name}' must be an object");
            }
        }

        // Update services
        foreach ($services as $service_name => $service) {
            self::$services[$app_slug][$service_name] = $service;
        }

        return true;
    }

    /**
     * Get the configuration for an application.
     *
     * @param string $app_slug Application slug
     * @return Config|null Configuration instance or null if not found
     */
    public static function getConfig(string $app_slug): ?Config
    {
        $app_slug = sanitize_key($app_slug);
        return self::$configs[$app_slug] ?? null;
    }

    /**
     * Check if an application is registered.
     *
     * @param string $app_slug Application slug
     * @return bool Whether the application exists
     */
    public static function hasApp(string $app_slug): bool
    {
        $app_slug = sanitize_key($app_slug);
        return isset(self::$configs[$app_slug]);
    }

    /**
     * Check if a service exists for an application.
     *
     * @param string $app_slug Application slug
     * @param string $service_name Service identifier
     * @return bool Whether the service exists
     */
    public static function has(string $app_slug, string $service_name): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            return false;
        }

        // Check for alias
        if (isset(self::$aliases[$app_slug][$service_name])) {
            $service_name = self::$aliases[$app_slug][$service_name];
        }

        return isset(self::$services[$app_slug][$service_name]) ||
            isset(self::$factories[$app_slug][$service_name]);
    }

    /**
     * Get all services for an application.
     *
     * @param string $app_slug Application slug
     * @return array<string, object> All services for the app
     * @throws RuntimeException If app doesn't exist
     */
    public static function getAll(string $app_slug): array
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered");
        }

        return self::$services[$app_slug] ?? [];
    }

    /**
     * Get all registered application slugs.
     *
     * @return array<string> List of application slugs
     */
    public static function getApps(): array
    {
        return array_keys(self::$configs);
    }

    /**
     * Remove a service from an application.
     *
     * @param string $app_slug Application slug
     * @param string $service_name Service identifier
     * @return bool Whether the service was removed
     */
    public static function remove(string $app_slug, string $service_name): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            return false;
        }

        $removed = false;

        // Remove service
        if (isset(self::$services[$app_slug][$service_name])) {
            unset(self::$services[$app_slug][$service_name]);
            $removed = true;
        }

        // Remove factory
        if (isset(self::$factories[$app_slug][$service_name])) {
            unset(self::$factories[$app_slug][$service_name]);
            $removed = true;
        }

        return $removed;
    }

    /**
     * Remove an entire application and all its services.
     *
     * @param string $app_slug Application slug
     * @return bool Whether the application was removed
     */
    public static function removeApp(string $app_slug): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            return false;
        }

        unset(
            self::$configs[$app_slug],
            self::$services[$app_slug],
            self::$factories[$app_slug],
            self::$aliases[$app_slug]
        );

        return true;
    }

    /**
     * Register a service factory for lazy loading.
     *
     * @param string $app_slug Application slug
     * @param string $service_name Service identifier
     * @param callable $factory Factory function that returns service instance
     * @return bool Success status
     * @throws RuntimeException If app doesn't exist
     */
    public static function factory(string $app_slug, string $service_name, callable $factory): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered");
        }

        if (!isset(self::$factories[$app_slug])) {
            self::$factories[$app_slug] = [];
        }

        self::$factories[$app_slug][$service_name] = $factory;

        return true;
    }

    /**
     * Register multiple service factories.
     *
     * @param string $app_slug Application slug
     * @param array<string, callable> $factories Factory functions
     * @return bool Success status
     */
    public static function factories(string $app_slug, array $factories): bool
    {
        foreach ($factories as $service_name => $factory) {
            self::factory($app_slug, $service_name, $factory);
        }

        return true;
    }

    /**
     * Register a service alias.
     *
     * @param string $app_slug Application slug
     * @param string $alias Alias name
     * @param string $service_name Real service name
     * @return bool Success status
     * @throws RuntimeException If app doesn't exist
     */
    public static function alias(string $app_slug, string $alias, string $service_name): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered");
        }

        if (!isset(self::$aliases[$app_slug])) {
            self::$aliases[$app_slug] = [];
        }

        self::$aliases[$app_slug][$alias] = $service_name;

        return true;
    }

    /**
     * Register multiple aliases.
     *
     * @param string $app_slug Application slug
     * @param array<string, string> $aliases Alias to service name mappings
     * @return bool Success status
     */
    public static function aliases(string $app_slug, array $aliases): bool
    {
        foreach ($aliases as $alias => $service_name) {
            self::alias($app_slug, $alias, $service_name);
        }

        return true;
    }

    /**
     * Clear all services for an application (keeps config).
     *
     * @param string $app_slug Application slug
     * @return bool Success status
     */
    public static function clear(string $app_slug): bool
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            return false;
        }

        self::$services[$app_slug] = [];
        self::$factories[$app_slug] = [];
        self::$aliases[$app_slug] = [];

        return true;
    }

    /**
     * Clear all applications and services.
     *
     * @return void
     */
    public static function clearAll(): void
    {
        self::$configs = [];
        self::$services = [];
        self::$factories = [];
        self::$aliases = [];
    }

    /**
     * Get registry statistics for debugging.
     *
     * @return array<string, mixed> Registry statistics
     */
    public static function getStats(): array
    {
        $stats = [
            'total_apps' => count(self::$configs),
            'apps' => [],
        ];

        foreach (self::$configs as $app_slug => $config) {
            $stats['apps'][$app_slug] = [
                'services_count' => count(self::$services[$app_slug] ?? []),
                'factories_count' => count(self::$factories[$app_slug] ?? []),
                'aliases_count' => count(self::$aliases[$app_slug] ?? []),
                'services' => array_keys(self::$services[$app_slug] ?? []),
                'factories' => array_keys(self::$factories[$app_slug] ?? []),
                'aliases' => self::$aliases[$app_slug] ?? [],
            ];
        }

        return $stats;
    }

    /**
     * Create a service resolver function for an application.
     *
     * @param string $app_slug Application slug
     * @return callable Resolver function
     * @throws RuntimeException If app doesn't exist
     */
    public static function resolver(string $app_slug): callable
    {
        $app_slug = sanitize_key($app_slug);

        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered");
        }

        return function (string $service_name) use ($app_slug) {
            return self::get($app_slug, $service_name);
        };
    }

    /**
     * Extract app slug from Config instance or string.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @return string Application slug
     * @throws InvalidArgumentException If parameter is invalid
     */
    private static function extractAppSlug(Config|string $config_or_slug): string
    {
        if ($config_or_slug instanceof Config) {
            return $config_or_slug->slug;
        }

        if (is_string($config_or_slug)) {
            return sanitize_key($config_or_slug);
        }

        throw new InvalidArgumentException('First parameter must be Config instance or string');
    }

    /**
     * Ensure an application exists in the registry.
     *
     * @param string $app_slug Application slug
     * @return void
     * @throws RuntimeException If app doesn't exist
     */
    private static function ensureAppExists(string $app_slug): void
    {
        if (!self::hasApp($app_slug)) {
            throw new RuntimeException("Application '{$app_slug}' is not registered. Use Registry::registerApp() first.");
        }

        // Initialize services array if needed
        if (!isset(self::$services[$app_slug])) {
            self::$services[$app_slug] = [];
        }
    }
}
