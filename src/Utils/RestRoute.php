<?php

/**
 * REST Route Helper
 *
 * @author Your Name <username@example.com>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/szepeviktor/starter-plugin
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Enhanced REST Route Helper class with multi-version support.
 *
 * Provides comprehensive API management with version control, deprecation handling,
 * and backward compatibility features for scalable WordPress REST APIs.
 */
class RestRoute
{
    /**
     * Base API namespace for all routes.
     */
    protected static string $base_namespace = '';

    /**
     * Registered API versions with their configurations.
     *
     * @var array<string, array{
     *     routes: array<string, array<string, mixed>>,
     *     middleware: array<int, array<callable>>,
     *     deprecated: bool,
     *     deprecation_date: string|null,
     *     removal_date: string|null,
     *     successor_version: string|null
     * }>
     */
    protected static array $versions = [];

    /**
     * Current default version.
     */
    protected static string $default_version = 'v1';

    /**
     * Global middleware applied to all versions.
     */
    protected static array $global_middleware = [];

    /**
     * API documentation metadata.
     */
    protected static array $api_docs = [];

    /**
     * Initialize the REST route system with multi-version support.
     *
     * @param string|null $base_namespace Custom base namespace
     * @param array<string> $supported_versions List of supported versions
     * @param string $default_version Default API version
     * @return bool Success status
     */
    public static function init(
        ?string $base_namespace = null,
        array $supported_versions = ['v1'],
        string $default_version = 'v1'
    ): bool {
        if ($base_namespace === null) {
            $plugin_slug = Config::get('slug') ?? 'wp-plugin';
            $base_namespace = str_replace('-', '_', $plugin_slug);
        }

        self::$base_namespace = sanitize_key($base_namespace);
        self::$default_version = sanitize_key($default_version);

        // Initialize supported versions
        foreach ($supported_versions as $version) {
            self::register_version($version);
        }

        add_action('rest_api_init', [self::class, 'register_all_routes']);
        add_action('wp_head', [self::class, 'add_api_discovery']);

        return true;
    }

    /**
     * Register a new API version.
     *
     * @param string $version Version identifier (e.g., 'v1', 'v2')
     * @param array<string, mixed> $config Version configuration
     * @return bool Success status
     */
    public static function register_version(string $version, array $config = []): bool
    {
        $version = sanitize_key($version);

        $defaults = [
            'routes' => [],
            'middleware' => [],
            'deprecated' => false,
            'deprecation_date' => null,
            'removal_date' => null,
            'successor_version' => null,
            'description' => '',
            'changelog' => [],
        ];

        self::$versions[$version] = array_merge($defaults, $config);

        return true;
    }

    /**
     * Deprecate an API version.
     *
     * @param string $version Version to deprecate
     * @param string $deprecation_date Deprecation date (ISO 8601)
     * @param string|null $removal_date Planned removal date
     * @param string|null $successor_version Recommended successor version
     * @return bool Success status
     */
    public static function deprecate_version(
        string $version,
        string $deprecation_date,
        ?string $removal_date = null,
        ?string $successor_version = null
    ): bool {
        if (!isset(self::$versions[$version])) {
            return false;
        }

        self::$versions[$version]['deprecated'] = true;
        self::$versions[$version]['deprecation_date'] = $deprecation_date;
        self::$versions[$version]['removal_date'] = $removal_date;
        self::$versions[$version]['successor_version'] = $successor_version;

        return true;
    }

    /**
     * Add a route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param array<string, mixed> $config Route configuration
     * @return bool Success status
     */
    public static function add_route(string $version, string $route, array $config): bool
    {
        $version = sanitize_key($version);

        if (!isset(self::$versions[$version])) {
            self::register_version($version);
        }

        $defaults = [
            'methods' => 'GET',
            'callback' => null,
            'permission_callback' => '__return_true',
            'args' => [],
            'validate_callback' => null,
            'sanitize_callback' => null,
            'deprecated' => false,
            'deprecation_message' => '',
            'rate_limit' => null,
            'cache_ttl' => null,
        ];

        $route = '/' . ltrim($route, '/');
        self::$versions[$version]['routes'][$route] = array_merge($defaults, $config);

        return true;
    }

    /**
     * Add multiple routes to a specific version.
     *
     * @param string $version API version
     * @param array<string, array<string, mixed>> $routes Routes to add
     * @return bool Success status
     */
    public static function add_routes(string $version, array $routes): bool
    {
        foreach ($routes as $route => $config) {
            self::add_route($version, $route, $config);
        }
        return true;
    }

    /**
     * Add a GET route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param callable $callback Route callback
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public static function get(string $version, string $route, callable $callback, array $args = []): bool
    {
        return self::add_route($version, $route, array_merge($args, [
            'methods' => 'GET',
            'callback' => $callback,
        ]));
    }

    /**
     * Add a POST route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param callable $callback Route callback
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public static function post(string $version, string $route, callable $callback, array $args = []): bool
    {
        return self::add_route($version, $route, array_merge($args, [
            'methods' => 'POST',
            'callback' => $callback,
        ]));
    }

    /**
     * Add a PUT route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param callable $callback Route callback
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public static function put(string $version, string $route, callable $callback, array $args = []): bool
    {
        return self::add_route($version, $route, array_merge($args, [
            'methods' => 'PUT',
            'callback' => $callback,
        ]));
    }

    /**
     * Add a DELETE route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param callable $callback Route callback
     * @param array<string, mixed> $args Additional arguments
     * @return bool Success status
     */
    public static function delete(string $version, string $route, callable $callback, array $args = []): bool
    {
        return self::add_route($version, $route, array_merge($args, [
            'methods' => 'DELETE',
            'callback' => $callback,
        ]));
    }

    /**
     * Copy routes from one version to another (for backward compatibility).
     *
     * @param string $from_version Source version
     * @param string $to_version Target version
     * @param array<string> $exclude_routes Routes to exclude from copying
     * @return bool Success status
     */
    public static function copy_routes(string $from_version, string $to_version, array $exclude_routes = []): bool
    {
        if (!isset(self::$versions[$from_version])) {
            return false;
        }

        if (!isset(self::$versions[$to_version])) {
            self::register_version($to_version);
        }

        foreach (self::$versions[$from_version]['routes'] as $route => $config) {
            if (!in_array($route, $exclude_routes, true)) {
                self::$versions[$to_version]['routes'][$route] = $config;
            }
        }

        return true;
    }

    /**
     * Add middleware to a specific version.
     *
     * @param string $version API version
     * @param callable $middleware Middleware function
     * @param int $priority Priority (lower = earlier)
     * @return bool Success status
     */
    public static function add_middleware(string $version, callable $middleware, int $priority = 10): bool
    {
        if (!isset(self::$versions[$version])) {
            self::register_version($version);
        }

        if (!isset(self::$versions[$version]['middleware'][$priority])) {
            self::$versions[$version]['middleware'][$priority] = [];
        }

        self::$versions[$version]['middleware'][$priority][] = $middleware;
        ksort(self::$versions[$version]['middleware']);

        return true;
    }

    /**
     * Add global middleware applied to all versions.
     *
     * @param callable $middleware Middleware function
     * @param int $priority Priority (lower = earlier)
     * @return bool Success status
     */
    public static function add_global_middleware(callable $middleware, int $priority = 10): bool
    {
        if (!isset(self::$global_middleware[$priority])) {
            self::$global_middleware[$priority] = [];
        }

        self::$global_middleware[$priority][] = $middleware;
        ksort(self::$global_middleware);

        return true;
    }

    /**
     * Register all routes for all versions with WordPress.
     *
     * @return void
     */
    public static function register_all_routes(): void
    {
        foreach (self::$versions as $version => $version_config) {
            $namespace = self::get_full_namespace($version);

            foreach ($version_config['routes'] as $route => $config) {
                $route_config = [
                    'methods' => $config['methods'],
                    'callback' => [self::class, 'handle_request'],
                    'permission_callback' => $config['permission_callback'],
                    'args' => self::build_route_args($config['args']),
                ];

                // Store metadata for the handler
                $route_config['route_meta'] = [
                    'version' => $version,
                    'original_config' => $config,
                    'version_config' => $version_config,
                ];

                register_rest_route($namespace, $route, $route_config);
            }
        }
    }

    /**
     * Handle incoming REST requests with version-aware processing.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response
     */
    public static function handle_request(WP_REST_Request $request)
    {
        try {
            // Extract version and route information
            $route_meta = self::extract_route_metadata($request);
            if (!$route_meta) {
                return new WP_Error(
                    'route_not_found',
                    __('Route not found', 'textdomain'),
                    ['status' => 404]
                );
            }

            $version = $route_meta['version'];
            $config = $route_meta['original_config'];
            $version_config = $route_meta['version_config'];

            // Check if version is deprecated
            $deprecation_response = self::handle_deprecation($version, $version_config);
            if ($deprecation_response instanceof WP_Error) {
                return $deprecation_response;
            }

            // Apply global middleware
            $global_middleware_response = self::apply_global_middleware($request, $route_meta);
            if ($global_middleware_response instanceof WP_Error || $global_middleware_response instanceof WP_REST_Response) {
                return $global_middleware_response;
            }

            // Apply version-specific middleware
            $version_middleware_response = self::apply_version_middleware($request, $route_meta);
            if ($version_middleware_response instanceof WP_Error || $version_middleware_response instanceof WP_REST_Response) {
                return $version_middleware_response;
            }

            // Validate request
            $validation_error = self::validate_request($request, $config);
            if ($validation_error) {
                return $validation_error;
            }

            // Sanitize request parameters
            $sanitized_request = self::sanitize_request($request, $config);

            // Call the route callback
            $callback = $config['callback'];
            if (!is_callable($callback)) {
                return new WP_Error(
                    'invalid_callback',
                    __('Invalid route callback', 'textdomain'),
                    ['status' => 500]
                );
            }

            $response = call_user_func($callback, $sanitized_request);

            // Format and enhance response with version metadata
            $formatted_response = self::format_response($response);

            if ($formatted_response instanceof WP_REST_Response) {
                // Add version headers
                $formatted_response->header('X-API-Version', $version);

                // Add deprecation warnings if applicable
                if ($deprecation_response instanceof WP_REST_Response) {
                    $deprecation_headers = $deprecation_response->get_headers();
                    foreach ($deprecation_headers as $header => $value) {
                        $formatted_response->header($header, $value);
                    }
                }
            }

            return $formatted_response;
        } catch (\Exception $e) {
            return new WP_Error(
                'internal_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get the full namespace for a specific version.
     *
     * @param string $version API version
     * @return string Full namespace
     */
    public static function get_full_namespace(string $version): string
    {
        return self::$base_namespace . '/' . $version;
    }

    /**
     * Get the URL for a specific route and version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param array<string, mixed> $params URL parameters
     * @return string Route URL
     */
    public static function get_route_url(string $version, string $route, array $params = []): string
    {
        $route = '/' . ltrim($route, '/');
        $namespace = self::get_full_namespace($version);
        $url = rest_url($namespace . $route);

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        return esc_url_raw($url);
    }

    /**
     * Get available API versions.
     *
     * @param bool $include_deprecated Whether to include deprecated versions
     * @return array<string> Available versions
     */
    public static function get_available_versions(bool $include_deprecated = true): array
    {
        if ($include_deprecated) {
            return array_keys(self::$versions);
        }

        return array_keys(array_filter(self::$versions, fn($config) => !$config['deprecated']));
    }

    /**
     * Get API documentation for all versions.
     *
     * @return array<string, mixed> API documentation
     */
    public static function get_api_documentation(): array
    {
        $docs = [
            'base_namespace' => self::$base_namespace,
            'default_version' => self::$default_version,
            'versions' => [],
        ];

        foreach (self::$versions as $version => $config) {
            $version_docs = [
                'version' => $version,
                'namespace' => self::get_full_namespace($version),
                'deprecated' => $config['deprecated'],
                'description' => $config['description'] ?? '',
                'routes' => [],
            ];

            if ($config['deprecated']) {
                $version_docs['deprecation_info'] = [
                    'deprecation_date' => $config['deprecation_date'],
                    'removal_date' => $config['removal_date'],
                    'successor_version' => $config['successor_version'],
                ];
            }

            foreach ($config['routes'] as $route => $route_config) {
                $version_docs['routes'][$route] = [
                    'methods' => $route_config['methods'],
                    'url' => self::get_route_url($version, $route),
                    'deprecated' => $route_config['deprecated'] ?? false,
                    'args' => $route_config['args'] ?? [],
                ];
            }

            $docs['versions'][$version] = $version_docs;
        }

        return $docs;
    }

    /**
     * Create a standardized success response.
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return WP_REST_Response Success response
     */
    public static function success_response(mixed $data = null, string $message = '', int $status = 200): WP_REST_Response
    {
        $response_data = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($message)) {
            $response_data['message'] = $message;
        }

        return new WP_REST_Response($response_data, $status);
    }

    /**
     * Create a standardized error response.
     *
     * @param string $code Error code
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @param int $status HTTP status code
     * @return WP_Error Error response
     */
    public static function error_response(string $code, string $message, mixed $data = null, int $status = 400): WP_Error
    {
        $error_data = ['status' => $status];

        if ($data !== null) {
            $error_data['data'] = $data;
        }

        return new WP_Error($code, $message, $error_data);
    }

    /**
     * Validate current user permissions.
     *
     * @param string $capability Required capability
     * @return bool|WP_Error True if allowed, WP_Error if not
     */
    public static function check_permissions(string $capability = 'read')
    {
        if (!current_user_can($capability)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource', 'textdomain'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Validate a nonce in the request.
     *
     * @param WP_REST_Request $request Request object
     * @param string $action Nonce action
     * @param string $param Parameter name containing nonce
     * @return bool|WP_Error True if valid, WP_Error if not
     */
    public static function verify_nonce(WP_REST_Request $request, string $action, string $param = '_wpnonce')
    {
        $nonce = $request->get_param($param);

        if (!wp_verify_nonce($nonce, $action)) {
            return new WP_Error(
                'rest_nonce_invalid',
                __('Invalid nonce', 'textdomain'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Add API discovery to HTML head.
     *
     * @return void
     */
    public static function add_api_discovery(): void
    {
        $default_namespace = self::get_full_namespace(self::$default_version);
        $api_url = rest_url($default_namespace);

        printf(
            '<link rel="https://api.w.org/" href="%s" />',
            esc_url($api_url)
        );
    }

    /**
     * Extract route metadata from request.
     *
     * @param WP_REST_Request $request Request object
     * @return array<string, mixed>|null Route metadata
     */
    protected static function extract_route_metadata(WP_REST_Request $request): ?array
    {
        // WordPress stores route metadata in the request attributes
        $route_attributes = $request->get_attributes();
        return $route_attributes['route_meta'] ?? null;
    }

    /**
     * Handle deprecation warnings and blocking.
     *
     * @param string $version API version
     * @param array<string, mixed> $version_config Version configuration
     * @return WP_REST_Response|WP_Error|null Deprecation response
     */
    protected static function handle_deprecation(string $version, array $version_config)
    {
        if (!$version_config['deprecated']) {
            return null;
        }

        $response = new WP_REST_Response();

        // Add deprecation headers
        $response->header('Warning', '299 - "API version deprecated"');
        $response->header('X-API-Deprecated', 'true');

        if ($version_config['deprecation_date']) {
            $response->header('X-API-Deprecation-Date', $version_config['deprecation_date']);
        }

        if ($version_config['removal_date']) {
            $response->header('X-API-Removal-Date', $version_config['removal_date']);
        }

        if ($version_config['successor_version']) {
            $response->header('X-API-Successor-Version', $version_config['successor_version']);
        }

        // Check if version should be blocked (past removal date)
        if ($version_config['removal_date'] && strtotime($version_config['removal_date']) < time()) {
            return new WP_Error(
                'api_version_removed',
                sprintf(
                    __('API version %s has been removed. Please use version %s.', 'textdomain'),
                    $version,
                    $version_config['successor_version'] ?? 'latest'
                ),
                ['status' => 410] // Gone
            );
        }

        return $response;
    }

    /**
     * Apply global middleware.
     *
     * @param WP_REST_Request $request Request object
     * @param array<string, mixed> $route_meta Route metadata
     * @return mixed Middleware response or null to continue
     */
    protected static function apply_global_middleware(WP_REST_Request $request, array $route_meta)
    {
        foreach (self::$global_middleware as $priority_group) {
            foreach ($priority_group as $middleware) {
                $result = call_user_func($middleware, $request, $route_meta);

                if ($result instanceof WP_Error || $result instanceof WP_REST_Response) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Apply version-specific middleware.
     *
     * @param WP_REST_Request $request Request object
     * @param array<string, mixed> $route_meta Route metadata
     * @return mixed Middleware response or null to continue
     */
    protected static function apply_version_middleware(WP_REST_Request $request, array $route_meta)
    {
        $version = $route_meta['version'];
        $version_config = $route_meta['version_config'];

        foreach ($version_config['middleware'] as $priority_group) {
            foreach ($priority_group as $middleware) {
                $result = call_user_func($middleware, $request, $route_meta);

                if ($result instanceof WP_Error || $result instanceof WP_REST_Response) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Validate request parameters.
     *
     * @param WP_REST_Request $request Request object
     * @param array<string, mixed> $config Route configuration
     * @return WP_Error|null Validation error or null if valid
     */
    protected static function validate_request(WP_REST_Request $request, array $config): ?WP_Error
    {
        $args = $config['args'] ?? [];

        foreach ($args as $param => $param_config) {
            $value = $request->get_param($param);

            // Check required parameters
            if (($param_config['required'] ?? false) && $value === null) {
                return new WP_Error(
                    'missing_parameter',
                    sprintf(__('Missing required parameter: %s', 'textdomain'), $param),
                    ['status' => 400]
                );
            }

            // Custom validation
            if (isset($param_config['validate_callback']) && is_callable($param_config['validate_callback'])) {
                $is_valid = call_user_func($param_config['validate_callback'], $value, $request, $param);

                if (!$is_valid) {
                    return new WP_Error(
                        'invalid_parameter',
                        sprintf(__('Invalid parameter: %s', 'textdomain'), $param),
                        ['status' => 400]
                    );
                }
            }
        }

        return null;
    }

    /**
     * Sanitize request parameters.
     *
     * @param WP_REST_Request $request Request object
     * @param array<string, mixed> $config Route configuration
     * @return WP_REST_Request Sanitized request
     */
    protected static function sanitize_request(WP_REST_Request $request, array $config): WP_REST_Request
    {
        $args = $config['args'] ?? [];

        foreach ($args as $param => $param_config) {
            $value = $request->get_param($param);

            if ($value !== null && isset($param_config['sanitize_callback']) && is_callable($param_config['sanitize_callback'])) {
                $sanitized_value = call_user_func($param_config['sanitize_callback'], $value, $request, $param);
                $request->set_param($param, $sanitized_value);
            }
        }

        return $request;
    }

    /**
     * Format response data.
     *
     * @param mixed $response Raw response
     * @return WP_REST_Response|WP_Error Formatted response
     */
    protected static function format_response(mixed $response)
    {
        if ($response instanceof WP_REST_Response || $response instanceof WP_Error) {
            return $response;
        }

        if (is_array($response) && isset($response['error'])) {
            return new WP_Error(
                $response['code'] ?? 'error',
                $response['error'],
                ['status' => $response['status'] ?? 400]
            );
        }

        return new WP_REST_Response($response, 200);
    }

    /**
     * Build WordPress REST API argument configuration.
     *
     * @param array<string, mixed> $args Raw arguments
     * @return array<string, mixed> WordPress-formatted arguments
     */
    protected static function build_route_args(array $args): array
    {
        $wp_args = [];

        foreach ($args as $param => $config) {
            $wp_config = [];

            $config_keys = ['description', 'type', 'required', 'default', 'enum', 'validate_callback', 'sanitize_callback'];

            foreach ($config_keys as $key) {
                if (isset($config[$key])) {
                    $wp_config[$key] = $config[$key];
                }
            }

            $wp_args[$param] = $wp_config;
        }

        return $wp_args;
    }
}
