<?php

/**
 * Autoloader Helper
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * Autoloader Helper class for automatic class loading.
 *
 * Provides PSR-4 compatible autoloading with support for multiple
 * namespaces, file mapping, and WordPress-specific optimizations.
 */
class Autoloader
{
    /**
     * Registered namespace prefixes and their base directories.
     */
    protected static array $namespace_map = [];

    /**
     * Class file mappings for direct loading.
     */
    protected static array $class_map = [];

    /**
     * Loaded classes cache.
     */
    protected static array $loaded_classes = [];

    /**
     * Whether the autoloader is registered.
     */
    protected static bool $is_registered = false;

    /**
     * File extensions to search for.
     */
    protected static array $file_extensions = ['.php'];

    /**
     * Track which plugins have initialized the autoloader
     */
    protected static array $initialized_plugins = [];

    /**
     * Initialize and register the autoloader.
     *
     * @param array<string, string> $namespace_map Initial namespace mappings
     * @param array<string, string> $class_map Initial class mappings
     * @param bool $overwrite Whether to overwrite existing mappings
     * @param string|null $plugin_id Optional plugin identifier for tracking
     * @return bool Success status
     */
    public static function init(array $namespace_map = [], array $class_map = [], bool $overwrite = false, ?string $plugin_id = null): bool
    {
        // Generate plugin ID if not provided
        if ($plugin_id === null) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $plugin_id = $backtrace[1]['file'] ?? 'unknown';
        }

        // Track this plugin's initialization
        if (!isset(self::$initialized_plugins[$plugin_id])) {
            self::$initialized_plugins[$plugin_id] = [
                'namespaces' => [],
                'classes' => [],
                'timestamp' => time()
            ];
        }

        $success = true;

        // Add namespace mappings and track them per plugin
        foreach ($namespace_map as $namespace => $base_dir) {
            if (self::add_namespace($namespace, $base_dir, $overwrite)) {
                self::$initialized_plugins[$plugin_id]['namespaces'][] = $namespace;
            } else {
                $success = false;
            }
        }

        // Add class mappings and track them per plugin
        foreach ($class_map as $class_name => $file_path) {
            if (self::add_class_map($class_name, $file_path, $overwrite)) {
                self::$initialized_plugins[$plugin_id]['classes'][] = $class_name;
            } else {
                $success = false;
            }
        }

        // Register the autoloader if not already registered
        if (!self::$is_registered) {
            $register_success = self::register();
            if (!$register_success) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Register the autoloader with PHP.
     *
     * @param bool $prepend Whether to prepend to the autoloader stack
     * @return bool Success status
     */
    public static function register(bool $prepend = false): bool
    {
        if (self::$is_registered) {
            return true;
        }

        $success = spl_autoload_register([self::class, 'load_class'], true, $prepend);

        if ($success) {
            self::$is_registered = true;
        }

        return $success;
    }

    /**
     * Unregister the autoloader.
     *
     * @return bool Success status
     */
    public static function unregister(): bool
    {
        if (!self::$is_registered) {
            return true;
        }

        $success = spl_autoload_unregister([self::class, 'load_class']);

        if ($success) {
            self::$is_registered = false;
            // Clear plugin tracking when unregistering
            self::$initialized_plugins = [];
        }

        return $success;
    }

    /**
     * Add a namespace mapping.
     *
     * @param string $namespace Namespace prefix
     * @param string $base_dir Base directory for the namespace
     * @param bool $overwrite Whether to overwrite existing mapping
     * @param bool $prepend Whether to prepend to the namespace list
     * @return bool Success status
     */
    public static function add_namespace(string $namespace, string $base_dir, bool $overwrite = false, bool $prepend = false): bool
    {
        // Normalize namespace
        $namespace = rtrim($namespace, '\\') . '\\';

        // Normalize directory path
        $base_dir = self::normalize_directory($base_dir);

        if (!is_dir($base_dir)) {
            return false;
        }

        // Check if namespace already exists
        if (!$overwrite && isset(self::$namespace_map[$namespace])) {
            if (self::$namespace_map[$namespace] === $base_dir) {
                return true; // Already exists with the same path
            } else {
                // Different path - this could be a conflict
                trigger_error(
                    "Namespace conflict: '{$namespace}' is already mapped to a different directory. " .
                        "Existing: '" . self::$namespace_map[$namespace] . "', " .
                        "New: '{$base_dir}'",
                    E_USER_WARNING
                );
                return false;
            }
        }

        if ($prepend) {
            self::$namespace_map = [$namespace => $base_dir] + self::$namespace_map;
        } else {
            self::$namespace_map[$namespace] = $base_dir;
        }

        return true;
    }

    /**
     * Add multiple namespace mappings.
     *
     * @param array<string, string> $namespaces Namespace to directory mappings
     * @param bool $overwrite Whether to overwrite existing mappings
     * @return bool Success status
     */
    public static function add_namespaces(array $namespaces, bool $overwrite = false): bool
    {
        $success = true;

        foreach ($namespaces as $namespace => $base_dir) {
            if (!self::add_namespace($namespace, $base_dir, $overwrite)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Add a direct class file mapping.
     *
     * @param string $class_name Fully qualified class name
     * @param string $file_path Path to the class file
     * @param bool $overwrite Whether to overwrite existing mapping
     * @return bool Success status
     */
    public static function add_class_map(string $class_name, string $file_path, bool $overwrite = false): bool
    {
        // Normalize class name
        $class_name = ltrim($class_name, '\\');

        // Normalize file path
        $file_path = self::normalize_directory($file_path);

        if (!is_file($file_path)) {
            return false; // File does not exist
        }

        // Check if class already exists
        if (!$overwrite && isset(self::$class_map[$class_name])) {
            if (self::$class_map[$class_name] === $file_path) {
                return true; // Already exists with the same path
            } else {
                // Different path - this could be a conflict
                trigger_error(
                    "Class mapping conflict: '{$class_name}' is already mapped to a different file. " .
                        "Existing: '" . self::$class_map[$class_name] . "', " .
                        "New: '{$file_path}'",
                    E_USER_WARNING
                );
                return false;
            }
        }

        self::$class_map[$class_name] = $file_path;
        return true;
    }

    /**
     * Add multiple class file mappings.
     *
     * @param array<string, string> $class_map Class to file mappings
     * @param bool $overwrite Whether to overwrite existing mappings
     * @return bool Success status
     */
    public static function add_class_maps(array $class_map, bool $overwrite = false): bool
    {
        $success = true;

        foreach ($class_map as $class_name => $file_path) {
            if (!self::add_class_map($class_name, $file_path, $overwrite)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Remove a plugin's registered namespaces and classes.
     *
     * @param string $plugin_id Plugin identifier
     * @return bool Success status
     */
    public static function remove_plugin_mappings(string $plugin_id): bool
    {
        if (!isset(self::$initialized_plugins[$plugin_id])) {
            return false;
        }

        $plugin_data = self::$initialized_plugins[$plugin_id];

        // Remove namespaces
        foreach ($plugin_data['namespaces'] as $namespace) {
            unset(self::$namespace_map[$namespace]);
        }

        // Remove class mappings
        foreach ($plugin_data['classes'] as $class_name) {
            unset(self::$class_map[$class_name]);
            unset(self::$loaded_classes[$class_name]);
        }

        // Remove plugin tracking
        unset(self::$initialized_plugins[$plugin_id]);

        return true;
    }

    /**
     * Get information about initialized plugins.
     *
     * @return array<string, array> Plugin initialization data
     */
    public static function get_initialized_plugins(): array
    {
        return self::$initialized_plugins;
    }

    /**
     * Check if a specific plugin has been initialized.
     *
     * @param string $plugin_id Plugin identifier
     * @return bool Whether the plugin has been initialized
     */
    public static function is_plugin_initialized(string $plugin_id): bool
    {
        return isset(self::$initialized_plugins[$plugin_id]);
    }

    /**
     * Load a class file.
     *
     * @param string $class_name Fully qualified class name
     * @return bool Whether the class was loaded
     */
    public static function load_class(string $class_name): bool
    {
        // Check if already loaded
        if (isset(self::$loaded_classes[$class_name])) {
            return self::$loaded_classes[$class_name];
        }

        // Try direct class mapping first
        if (isset(self::$class_map[$class_name])) {
            $file_path = self::$class_map[$class_name];
            if (self::load_file($file_path)) {
                self::$loaded_classes[$class_name] = true;
                return true;
            }
        }

        // Try namespace mappings (order matters - first match wins)
        foreach (self::$namespace_map as $namespace => $base_dir) {
            if (str_starts_with($class_name . '\\', $namespace)) {
                $relative_class = substr($class_name, strlen($namespace));
                $file_path = self::get_file_path($base_dir, $relative_class);

                if (self::load_file($file_path)) {
                    self::$loaded_classes[$class_name] = true;
                    return true;
                }
            }
        }

        // Class not found
        self::$loaded_classes[$class_name] = false;
        return false;
    }

    /**
     * Check if a class can be loaded by this autoloader.
     *
     * @param string $class_name Fully qualified class name
     * @return bool Whether the class can be loaded
     */
    public static function can_load_class(string $class_name): bool
    {
        // Check direct class mapping
        if (isset(self::$class_map[$class_name])) {
            return file_exists(self::$class_map[$class_name]);
        }

        // Check namespace mappings
        foreach (self::$namespace_map as $namespace => $base_dir) {
            if (str_starts_with($class_name . '\\', $namespace)) {
                $relative_class = substr($class_name, strlen($namespace));
                $file_path = self::get_file_path($base_dir, $relative_class);

                if (file_exists($file_path)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all registered namespaces.
     *
     * @return array<string, string> Namespace mappings
     */
    public static function get_namespaces(): array
    {
        return self::$namespace_map;
    }

    /**
     * Get all registered class mappings.
     *
     * @return array<string, string> Class mappings
     */
    public static function get_class_maps(): array
    {
        return self::$class_map;
    }

    /**
     * Get all loaded classes.
     *
     * @return array<string, bool> Loaded classes with success status
     */
    public static function get_loaded_classes(): array
    {
        return self::$loaded_classes;
    }

    /**
     * Clear the loaded classes cache.
     *
     * @return void
     */
    public static function clear_cache(): void
    {
        self::$loaded_classes = [];
    }

    /**
     * Add file extensions to search for.
     *
     * @param array<string> $extensions File extensions with dots
     * @return void
     */
    public static function add_file_extensions(array $extensions): void
    {
        foreach ($extensions as $extension) {
            if (!in_array($extension, self::$file_extensions, true)) {
                self::$file_extensions[] = $extension;
            }
        }
    }

    /**
     * Generate a class map for a directory.
     *
     * @param string $directory Directory to scan
     * @param string $namespace_prefix Namespace prefix for classes
     * @param bool $recursive Whether to scan recursively
     * @return array<string, string> Generated class map
     */
    public static function generate_class_map(string $directory, string $namespace_prefix = '', bool $recursive = true): array
    {
        $class_map = [];
        $directory = self::normalize_directory($directory);

        if (!is_dir($directory)) {
            return $class_map;
        }

        $namespace_prefix = rtrim($namespace_prefix, '\\');
        $files = self::scan_directory($directory, $recursive);

        foreach ($files as $file) {
            $class_name = self::extract_class_name($file, $directory, $namespace_prefix);
            if ($class_name) {
                $class_map[$class_name] = $file;
            }
        }

        return $class_map;
    }

    /**
     * Load and cache a generated class map.
     *
     * @param string $directory Directory to scan
     * @param string $namespace_prefix Namespace prefix
     * @param bool $recursive Whether to scan recursively
     * @return bool Success status
     */
    public static function load_class_map_from_directory(string $directory, string $namespace_prefix = '', bool $recursive = true): bool
    {
        $class_map = self::generate_class_map($directory, $namespace_prefix, $recursive);
        return self::add_class_maps($class_map);
    }

    /**
     * Get file path for a class based on PSR-4 standard.
     *
     * @param string $base_dir Base directory
     * @param string $relative_class Relative class name
     * @return string File path
     */
    protected static function get_file_path(string $base_dir, string $relative_class): string
    {
        // Replace namespace separators with directory separators
        $file_path = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

        return $base_dir . $file_path;
    }

    /**
     * Load a file if it exists.
     *
     * @param string $file_path Path to the file
     * @return bool Whether the file was loaded
     */
    protected static function load_file(string $file_path): bool
    {
        if (file_exists($file_path)) {
            require_once $file_path;
            return true;
        }

        return false;
    }

    /**
     * Normalize a directory path.
     *
     * @param string $directory Directory path
     * @return string Normalized directory path
     */
    protected static function normalize_directory(string $directory): string
    {
        $directory = rtrim(str_replace('\\', DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
        return $directory . DIRECTORY_SEPARATOR;
    }

    /**
     * Scan directory for PHP files.
     *
     * @param string $directory Directory to scan
     * @param bool $recursive Whether to scan recursively
     * @return array<string> Found PHP files
     */
    protected static function scan_directory(string $directory, bool $recursive = true): array
    {
        $files = [];

        if (!is_dir($directory)) {
            return $files;
        }

        $iterator = $recursive
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory))
            : new \DirectoryIterator($directory);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = '.' . $file->getExtension();
                if (in_array($extension, self::$file_extensions, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * Extract class name from a PHP file.
     *
     * @param string $file_path Path to the PHP file
     * @param string $base_dir Base directory
     * @param string $namespace_prefix Namespace prefix
     * @return string|null Class name or null if not found
     */
    protected static function extract_class_name(string $file_path, string $base_dir, string $namespace_prefix): ?string
    {
        $relative_path = str_replace($base_dir, '', $file_path);
        $relative_path = ltrim(str_replace(DIRECTORY_SEPARATOR, '\\', $relative_path), '\\');
        $class_path = str_replace('.php', '', $relative_path);

        if (!empty($namespace_prefix)) {
            $full_class = $namespace_prefix . '\\' . $class_path;
        } else {
            $full_class = $class_path;
        }

        // Validate that the file actually contains this class
        if (self::file_contains_class($file_path, $full_class)) {
            return $full_class;
        }

        return null;
    }

    /**
     * Check if a file contains a specific class.
     *
     * @param string $file_path Path to the PHP file
     * @param string $class_name Expected class name
     * @return bool Whether the file contains the class
     */
    protected static function file_contains_class(string $file_path, string $class_name): bool
    {
        if (!file_exists($file_path)) {
            return false;
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            return false;
        }

        // Extract just the class name without namespace
        $class_parts = explode('\\', $class_name);
        $simple_class_name = end($class_parts);

        // Look for class, interface, or trait declaration
        $patterns = [
            '/^\s*(?:final\s+)?(?:abstract\s+)?class\s+' . preg_quote($simple_class_name, '/') . '\s*(?:\{|extends|implements)/m',
            '/^\s*interface\s+' . preg_quote($simple_class_name, '/') . '\s*(?:\{|extends)/m',
            '/^\s*trait\s+' . preg_quote($simple_class_name, '/') . '\s*\{/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dump autoloader information for debugging.
     *
     * @return array<string, mixed> Autoloader debug information
     */
    public static function dump_info(): array
    {
        return [
            'is_registered' => self::$is_registered,
            'namespace_map' => self::$namespace_map,
            'class_map' => self::$class_map,
            'loaded_classes' => self::$loaded_classes,
            'file_extensions' => self::$file_extensions,
            'initialized_plugins' => self::$initialized_plugins,
        ];
    }
}
