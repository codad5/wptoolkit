<?php

/**
 * Updated API Helper using Cache Utility
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use Codad5\WPToolkit\Utils\Cache;

/**
 * Abstract API Helper class for making HTTP requests and managing API responses.
 *
 * Now uses the Cache utility class for improved caching capabilities.
 */
abstract class APIHelper
{
    /**
     * The name identifier for this API helper instance.
     */
    protected static string $name = 'APIHelper';

    /**
     * Cache group for this API helper.
     */
    protected static string $cache_group = 'api_helper';

    /**
     * Default cache duration in minutes.
     */
    protected static int $default_cache_duration = 90;

	/**
	 * API endpoints configuration - should be overridden in child classes.
	 *
	 * @return array<string, array{
	 *     route: string,
	 *     method?: string,
	 *     params?: array<string, mixed>,
	 *     body?: array<string, mixed>,
	 *     headers?: array<string, string>,
	 *     cache?: bool|int|callable
	 * }>
	 */
	abstract protected static function get_endpoints(): array;;

    /**
     * Get the plugin slug for cache and option key generation.
     *
     * Child classes must implement this.
     *
     * @return string Plugin slug
     */
    abstract protected static function get_slug(): string;

    /**
     * Get the base API host URL.
     *
     * Child classes must implement this.
     *
     * @return string Base API host URL
     */
    abstract protected static function get_base_url(): string;

    /**
     * Get headers for API requests.
     *
     * Child classes must implement this.
     *
     * @return array<string, string> Headers array
     */
    abstract protected static function get_headers(): array;

    /**
     * Prepare a complete request URL with query parameters.
     *
     * @param string $url Base URL
     * @param array<string, mixed> $params Query parameters
     * @return string Complete URL with parameters
     */
    public static function prepare_request_url(string $url, array $params = []): string
    {
        if (empty($params)) {
            return $url;
        }

        $url = $url . '?' . http_build_query($params);
        return rtrim($url, '?#');
    }

    /**
     * Cache data using the Cache utility.
     *
     * @param string $key Cache key
     * @param mixed $value Data to cache
     * @param int|null $expiration Expiration time in minutes (default: 90)
     * @return bool True on success, false on failure
     */
    public static function cache(string $key, mixed $value, ?int $expiration = null): bool
    {
        $expiration = $expiration ?? static::$default_cache_duration;
        $cache_key = static::generate_cache_key($key);

        return Cache::set($cache_key, $value, $expiration * 60, static::get_cache_group());
    }

    /**
     * Get cached data using the Cache utility.
     *
     * @param string $key Cache key
     * @return mixed Cached data or false if not found
     */
    public static function get_cache(string $key): mixed
    {
        $cache_key = static::generate_cache_key($key);
        return Cache::get($cache_key, false, static::get_cache_group());
    }

    /**
     * Delete specific cached data.
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public static function delete_cache(string $key): bool
    {
        $cache_key = static::generate_cache_key($key);
        return Cache::delete($cache_key, static::get_cache_group());
    }

    /**
     * Clear all cached data for this API helper.
     *
     * @return int Number of cache entries cleared
     */
    public static function clear_cache(): int
    {
        return Cache::clear_group(static::get_cache_group());
    }

    /**
     * List all cached data for this API helper.
     *
     * @return array Array of cached data keyed by cache key
     */
    public static function list_cache(): array
    {
        return Cache::list_group(static::get_cache_group());
    }

    /**
     * Get cache statistics for this API helper.
     *
     * @return array Cache statistics
     */
    public static function get_cache_stats(): array
    {
        return Cache::get_stats(static::get_cache_group());
    }

    /**
     * Remember a value - get from cache or compute and cache if not exists.
     *
     * @param string $key Cache key
     * @param callable $callback Function to compute value if cache miss
     * @param int|null $expiration Expiration time in minutes
     * @return mixed Cached or computed value
     */
    public static function remember(string $key, callable $callback, ?int $expiration = null): mixed
    {
        $expiration = $expiration ?? static::$default_cache_duration;
        $cache_key = static::generate_cache_key($key);

        return Cache::remember(
            $cache_key,
            $callback,
            $expiration * 60,
            static::get_cache_group()
        );
    }

    /**
     * Make an HTTP request.
     *
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array<string, mixed> $params Request parameters
     * @param array<string, mixed> $body Request body data
     * @param array<string, mixed> $args Additional request arguments
     * @return mixed Response data
     * @throws \Exception When request fails or response is invalid
     */
	public static function request(string $method, string $url, array $params = [], array $body = [], array $args = []): mixed
	{
		$method = strtoupper(sanitize_text_field($method));
		$url = esc_url_raw($url);

		$request_args = array_merge([
			'method' => $method,
			'timeout' => 30,
		], $args, [
			'headers' => array_merge(
				static::get_headers(),
				$args['headers'] ?? []
			),
		]);

		// Add query parameters to URL for all methods
		if (!empty($params)) {
			$url = static::prepare_request_url($url, $params);
		}

		// Add body for non-GET requests
		if ($method !== 'GET' && !empty($body)) {
			$content_type = static::resolve_content_type($request_args['headers']);
			$request_args['body'] = static::prepare_request_body($body, $content_type);
		}

		$response = wp_remote_request($url, $request_args);

		if (is_wp_error($response)) {
			throw new \Exception(
				sprintf(
					'Request failed: %s (Code: %s)',
					esc_html($response->get_error_message()),
					esc_html($response->get_error_code())
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		if ($status_code >= 400) {
			throw new \Exception(
				sprintf('HTTP Error: %d', $status_code)
			);
	    }

		$body_response = wp_remote_retrieve_body($response);
		$data = json_decode($body_response, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new \Exception(
				sprintf(
					'JSON decode error: %s',
					json_last_error_msg()
				)
			);
	    }

		// Handle caching
		$cache_setting = $args['cache'] ?? true;
		$should_cache = static::should_cache_response($cache_setting, $data);

		if ($should_cache && $method === 'GET') {
			$cache_duration = is_numeric($cache_setting) ? (int)$cache_setting : static::$default_cache_duration;
			static::cache($url, $data, $cache_duration);
		}

		static::update_api_call_count();
		return $data;
	}

	/**
	 * Prepare request body based on content type and data.
	 *
	 * @param array<string, mixed> $body Request body data
	 * @param string $content_type Content type header value
	 * @param bool $force_json_encode Force JSON encoding regardless of content type
	 * @return string|array Prepared request body
	 */
	protected static function prepare_request_body(array $body, string $content_type = 'application/json', bool $force_json_encode = true): string|array
	{
		if (empty($body)) {
			return [];
		}

		// Force JSON encoding by default or when content type is JSON
		if ($force_json_encode || str_contains($content_type, 'application/json')) {
			return json_encode($body);
		}

		// Handle form data
		if (str_contains($content_type, 'application/x-www-form-urlencoded')) {
			return http_build_query($body);
		}

		// Handle multipart form data or other types - return as array
		return $body;
	}

	/**
	 * Get default content type for requests.
	 *
	 * @return string Default content type
	 */
	protected static function get_default_content_type(): string
	{
		return 'application/json';
	}

	/**
	 * Determine content type from request headers or use default.
	 *
	 * @param array<string, string> $headers Request headers
	 * @return string Content type
	 */
	protected static function resolve_content_type(array $headers): string
	{
		return $headers['Content-Type'] ?? static::get_default_content_type();
	}

    /**
     * Determine if response should be cached.
     *
     * @param mixed $cache_setting Cache configuration
     * @param mixed $data Response data
     * @return bool Whether to cache the response
     */
    protected static function should_cache_response(mixed $cache_setting, mixed $data): bool
    {
        if (is_callable($cache_setting)) {
            try {
                return (bool)$cache_setting($data);
            } catch (\Exception $e) {
                error_log(
                    sprintf(
                        'Cache callback error in %s: %s',
                        static::get_name(),
                        $e->getMessage()
                    )
                );
                return false;
            }
        }

        return (bool)$cache_setting;
    }

    /**
     * Update API call count with automatic reset after 30 days.
     *
     * @return void
     */
    protected static function update_api_call_count(): void
    {
        $count_key = static::get_option_key('api_calls');
        $date_key = static::get_option_key('api_calls_start_date');

        $api_calls = get_option($count_key, 0);
        $start_date = get_option($date_key);

        // Initialize start date if this is the first call
        if (empty($start_date)) {
            update_option($date_key, gmdate('Y-m-d'));
        }

        // Reset counter if more than 30 days have passed
        if ($start_date && strtotime($start_date) < strtotime('-30 days')) {
            update_option($count_key, 0);
            update_option($date_key, gmdate('Y-m-d'));
            $api_calls = 0;
        }

        update_option($count_key, absint($api_calls) + 1);
    }

    /**
     * Get API call count for this helper.
     *
     * @return int Number of API calls made
     */
    public static function get_api_call_count(): int
    {
        $count_key = static::get_option_key('api_calls');
        return absint(get_option($count_key, 0));
    }

    /**
     * Get the name identifier for this API helper.
     *
     * @return string Helper name
     */
    public static function get_name(): string
    {
        return static::$name;
    }

    /**
     * Set the name identifier for this API helper.
     *
     * @param string $name Helper name
     * @return void
     */
    public static function set_name(string $name): void
    {
        static::$name = sanitize_key($name);
        static::$cache_group = static::$name . '_api';
    }

    /**
     * Set cache duration for this API helper.
     *
     * @param int $duration Cache duration in minutes
     * @return void
     */
    public static function set_cache_duration(int $duration): void
    {
        static::$default_cache_duration = $duration;
    }

    /**
     * Make a request using predefined endpoints.
     *
     * @param string $endpoint_name Endpoint identifier
     * @param array<string, mixed> $params Request parameters
     * @param array<string, string> $substitutions URL placeholder substitutions
     * @return mixed Response data
     * @throws \Exception When endpoint is invalid or request fails
     */
    public static function make_request(string $endpoint_name, array $params = [], array $substitutions = []): mixed
    {
        $endpoint = static::get_endpoints()[$endpoint_name] ?? null;
        if (!$endpoint) {
            throw new \Exception(
                sprintf('Invalid endpoint name: %s', esc_html($endpoint_name))
            );
        }

        $route = $endpoint['route'] ?? null;
        if (!$route) {
            throw new \Exception(
                sprintf('Invalid endpoint route for: %s', esc_html($endpoint_name))
            );
        }

        // Substitute placeholders in route
        $route = static::substitute_placeholders($route, $substitutions);

        // Merge endpoint params with provided params
        $endpoint_params = $endpoint['params'] ?? [];
        $params = array_merge($endpoint_params, $params);

        // Filter params to only include those defined in endpoint
        if (!empty($endpoint_params)) {
            $params = array_intersect_key($params, $endpoint_params);
        }

        // Execute callable parameters
        foreach ($params as $key => $value) {
            if (is_callable($value)) {
                $params[$key] = $value();
            }
        }

        $method = $endpoint['method'] ?? 'GET';
        $url = rtrim(static::get_base_url(), '/') . '/' . ltrim($route, '/');

        // Check cache for GET requests
        if (strtoupper($method) === 'GET') {
            $cache_url = static::prepare_request_url($url, $params);
            $cached_data = static::get_cache($cache_url);

            if ($cached_data !== false) {
                return $cached_data;
            }

            $url = $cache_url;
            $params = []; // Parameters already in URL
        }

        return static::request($method, $url, $params, $endpoint);
    }

    /**
     * Substitute placeholders in route strings.
     *
     * @param string $route Route with placeholders like {{id}}
     * @param array<string, string> $substitutions Substitution values
     * @return string Route with substituted values
     * @throws \Exception When required substitution is missing
     */
    protected static function substitute_placeholders(string $route, array $substitutions): string
    {
        preg_match_all('/{{(.*?)}}/', $route, $matches);
        $placeholders = $matches[1] ?? [];

        foreach ($placeholders as $placeholder) {
            $value = $substitutions[$placeholder] ?? null;
            if ($value === null || $value === '') {
                throw new \Exception(
                    sprintf('Missing substitution value for: %s', esc_html($placeholder))
                );
            }

            $route = str_replace(
                '{{' . $placeholder . '}}',
                esc_attr($value),
                $route
            );
        }

        return $route;
    }

    /**
     * Generate a cache key for this API helper.
     *
     * @param string $key Base cache key
     * @return string Generated cache key
     */
    protected static function generate_cache_key(string $key): string
    {
        return sprintf('%s_%s', static::get_name(), md5($key));
    }

    /**
     * Get the cache group for this API helper.
     *
     * @return string Cache group
     */
    protected static function get_cache_group(): string
    {
        return static::$cache_group;
    }

    /**
     * Generate a prefixed option key.
     *
     * @param string $key Base option key
     * @return string Prefixed option key
     */
    protected static function get_option_key(string $key): string
    {
        return sprintf(
            '%s_%s_%s',
            static::get_slug(),
            static::get_name(),
            sanitize_key($key)
        );
    }
}
