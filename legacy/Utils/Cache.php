<?php

/**
 * Cache Utility
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

/**
 * Cache utility class for WordPress transient management.
 * 
 * Provides a clean interface for caching operations using WordPress transients
 * with automatic key prefixing, bulk operations, and cache tracking.
 */
class Cache
{
    /**
     * Default cache expiration in seconds (1 hour)
     */
    private const DEFAULT_EXPIRATION = 3600;

    /**
     * Maximum key length for cache keys
     */
    private const MAX_KEY_LENGTH = 172; // WordPress transient limit is 172 chars

    /**
     * Cache key tracker suffix
     */
    private const TRACKER_SUFFIX = '_cache_tracker';

    /**
     * Set a cache value.
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $expiration Expiration time in seconds (null for default)
     * @param string $group Optional cache group for organization
     * @return bool True on success, false on failure
     */
    public static function set(string $key, mixed $value, ?int $expiration = null, string $group = 'default'): bool
    {
        $expiration = $expiration ?? self::DEFAULT_EXPIRATION;
        $cache_key = self::generate_key($key, $group);

        if (strlen($cache_key) > self::MAX_KEY_LENGTH) {
            $cache_key = self::generate_key(md5($key), $group);
        }

        $success = set_transient($cache_key, $value, $expiration);

        if ($success) {
            self::track_cache_key($cache_key, $group, $expiration);
        }

        return $success;
    }

    /**
     * Get a cached value.
     *
     * @param string $key Cache key
     * @param mixed $default Default value if cache miss
     * @param string $group Optional cache group
     * @return mixed Cached value or default
     */
    public static function get(string $key, mixed $default = false, string $group = 'default'): mixed
    {
        $cache_key = self::generate_key($key, $group);

        if (strlen($cache_key) > self::MAX_KEY_LENGTH) {
            $cache_key = self::generate_key(md5($key), $group);
        }

        $value = get_transient($cache_key);
        return $value !== false ? $value : $default;
    }

    /**
     * Delete a specific cache entry.
     *
     * @param string $key Cache key
     * @param string $group Optional cache group
     * @return bool True on success, false on failure
     */
    public static function delete(string $key, string $group = 'default'): bool
    {
        $cache_key = self::generate_key($key, $group);

        if (strlen($cache_key) > self::MAX_KEY_LENGTH) {
            $cache_key = self::generate_key(md5($key), $group);
        }

        $success = delete_transient($cache_key);

        if ($success) {
            self::untrack_cache_key($cache_key, $group);
        }

        return $success;
    }

    /**
     * Check if a cache key exists.
     *
     * @param string $key Cache key
     * @param string $group Optional cache group
     * @return bool True if exists, false otherwise
     */
    public static function exists(string $key, string $group = 'default'): bool
    {
        $cache_key = self::generate_key($key, $group);

        if (strlen($cache_key) > self::MAX_KEY_LENGTH) {
            $cache_key = self::generate_key(md5($key), $group);
        }

        return get_transient($cache_key) !== false;
    }

    /**
     * Remember a value - get from cache or compute and cache if not exists.
     *
     * @param string $key Cache key
     * @param callable $callback Function to compute value if cache miss
     * @param int|null $expiration Expiration time in seconds
     * @param string $group Optional cache group
     * @return mixed Cached or computed value
     */
    public static function remember(string $key, callable $callback, ?int $expiration = null, string $group = 'default'): mixed
    {
        $value = self::get($key, null, $group);

        if ($value === null) {
            $value = $callback();
            self::set($key, $value, $expiration, $group);
        }

        return $value;
    }

    /**
     * Set multiple cache values at once.
     *
     * @param array $items Array of key => value pairs
     * @param int|null $expiration Expiration time in seconds
     * @param string $group Optional cache group
     * @return array Array of key => success_boolean pairs
     */
    public static function set_many(array $items, ?int $expiration = null, string $group = 'default'): array
    {
        $results = [];

        foreach ($items as $key => $value) {
            $results[$key] = self::set($key, $value, $expiration, $group);
        }

        return $results;
    }

    /**
     * Get multiple cache values at once.
     *
     * @param array $keys Array of cache keys
     * @param mixed $default Default value for cache misses
     * @param string $group Optional cache group
     * @return array Array of key => value pairs
     */
    public static function get_many(array $keys, mixed $default = false, string $group = 'default'): array
    {
        $results = [];

        foreach ($keys as $key) {
            $results[$key] = self::get($key, $default, $group);
        }

        return $results;
    }

    /**
     * Clear all cached data for a specific group.
     *
     * @param string $group Cache group to clear (default clears all)
     * @return int Number of cache entries cleared
     */
    public static function clear_group(string $group = 'default'): int
    {
        $tracker_key = self::generate_tracker_key($group);
        $cache_keys = get_transient($tracker_key);
        $cleared = 0;

        if (is_array($cache_keys)) {
            foreach ($cache_keys as $key) {
                if (delete_transient($key)) {
                    $cleared++;
                }
            }
        }

        delete_transient($tracker_key);
        return $cleared;
    }

    /**
     * Clear all cached data across all groups.
     *
     * @return int Number of cache entries cleared
     */
    public static function clear_all(): int
    {
        global $wpdb;

        $cleared = 0;

        // Clear all WPToolkit transients
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_wptoolkit_%'
            )
        );

        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient->option_name);
            if (delete_transient($key)) {
                $cleared++;
            }
        }

        return $cleared;
    }

    /**
     * List all cached data for a specific group.
     *
     * @param string $group Cache group
     * @return array Array of cached data keyed by cache key
     */
    public static function list_group(string $group = 'default'): array
    {
        $tracker_key = self::generate_tracker_key($group);
        $cache_keys = get_transient($tracker_key);
        $caches = [];

        if (is_array($cache_keys)) {
            foreach ($cache_keys as $key) {
                $data = get_transient($key);
                if ($data !== false) {
                    $caches[$key] = $data;
                }
            }
        }

        return $caches;
    }

    /**
     * Get cache statistics for a group.
     *
     * @param string $group Cache group
     * @return array Cache statistics (count, size estimate)
     */
    public static function get_stats(string $group = 'default'): array
    {
        $tracker_key = self::generate_tracker_key($group);
        $cache_keys = get_transient($tracker_key);
        $stats = [
            'count' => 0,
            'size_estimate' => 0,
            'keys' => []
        ];

        if (is_array($cache_keys)) {
            $stats['count'] = count($cache_keys);
            $stats['keys'] = $cache_keys;

            foreach ($cache_keys as $key) {
                $data = get_transient($key);
                if ($data !== false) {
                    $stats['size_estimate'] += strlen(serialize($data));
                }
            }
        }

        return $stats;
    }

    /**
     * Flush expired cache entries for a group.
     *
     * @param string $group Cache group
     * @return int Number of expired entries removed
     */
    public static function flush_expired(string $group = 'default'): int
    {
        $tracker_key = self::generate_tracker_key($group);
        $cache_keys = get_transient($tracker_key);
        $removed = 0;

        if (is_array($cache_keys)) {
            $valid_keys = [];

            foreach ($cache_keys as $key) {
                $data = get_transient($key);
                if ($data !== false) {
                    $valid_keys[] = $key;
                } else {
                    $removed++;
                }
            }

            // Update tracker with only valid keys
            if (!empty($valid_keys)) {
                set_transient($tracker_key, $valid_keys, DAY_IN_SECONDS);
            } else {
                delete_transient($tracker_key);
            }
        }

        return $removed;
    }

    /**
     * Generate a cache key with proper prefixing.
     *
     * @param string $key Base cache key
     * @param string $group Cache group
     * @return string Prefixed cache key
     */
    private static function generate_key(string $key, string $group): string
    {
        return sprintf('wptoolkit_%s_%s', sanitize_key($group), sanitize_key($key));
    }

    /**
     * Generate a tracker key for a cache group.
     *
     * @param string $group Cache group
     * @return string Tracker key
     */
    private static function generate_tracker_key(string $group): string
    {
        return sprintf('wptoolkit_%s%s', sanitize_key($group), self::TRACKER_SUFFIX);
    }

    /**
     * Track a cache key for bulk operations.
     *
     * @param string $cache_key The cache key to track
     * @param string $group Cache group
     * @param int $expiration Expiration in seconds
     * @return void
     */
    private static function track_cache_key(string $cache_key, string $group, int $expiration): void
    {
        $tracker_key = self::generate_tracker_key($group);
        $cache_keys = get_transient($tracker_key);

        if (!is_array($cache_keys)) {
            $cache_keys = [];
        }

        $cache_keys = array_unique(array_merge($cache_keys, [$cache_key]));

        // Set tracker expiration to be longer than individual cache items
        $tracker_expiration = max($expiration, DAY_IN_SECONDS);
        set_transient($tracker_key, $cache_keys, $tracker_expiration);
    }

    /**
     * Remove a cache key from tracking.
     *
     * @param string $cache_key The cache key to untrack
     * @param string $group Cache group
     * @return void
     */
    private static function untrack_cache_key(string $cache_key, string $group): void
    {
        $tracker_key = self::generate_tracker_key($group);
        $cache_keys = get_transient($tracker_key);

        if (is_array($cache_keys)) {
            $cache_keys = array_diff($cache_keys, [$cache_key]);

            if (!empty($cache_keys)) {
                set_transient($tracker_key, $cache_keys, DAY_IN_SECONDS);
            } else {
                delete_transient($tracker_key);
            }
        }
    }
}
