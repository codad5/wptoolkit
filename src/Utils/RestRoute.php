<?php

/**
 * REST Route Helper
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use InvalidArgumentException;
use function current_user_can;

/**
 * Enhanced REST Route Helper class with multi-version support.
 *
 * Provides comprehensive API management with version control, deprecation handling,
 * and backward compatibility features for scalable WordPress REST APIs.
 * Now fully object-based with dependency injection support.
 */
class RestRoute
{
    /**
     * Base API namespace for all routes.
     */
    protected string $base_namespace;

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
    protected array $versions = [];

    /**
     * Current default version.
     */
    protected string $default_version = 'v1';

    /**
     * Global middleware applied to all versions.
     *
     * @var array<int, array<callable>>
     */
    protected array $global_middleware = [];

    /**
     * API documentation metadata.
     *
     * @var array<string, mixed>
     */
    protected array $api_docs = [];

    /**
     * Application slug for identification.
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
     * Whether hooks have been registered.
     */
    protected bool $hooks_registered = false;

    /**
     * Constructor for creating a new RestRoute instance.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param array<string> $supported_versions List of supported versions
     * @param string $default_version Default API version
     * @param string|null $base_namespace Custom base namespace
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(
        Config|string $config_or_slug,
        array $supported_versions = ['v1'],
        string $default_version = 'v1',
        ?string $base_namespace = null
    ) {
        $this->parseConfigOrSlug($config_or_slug);
        $this->base_namespace = $base_namespace ?? str_replace('-', '_', $this->app_slug);
        $this->default_version = sanitize_key($default_version);

        // Initialize supported versions
        foreach ($supported_versions as $version) {
            $this->registerVersion($version);
        }

        $this->registerHooks();
    }

    /**
     * Static factory method for creating RestRoute instances.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param array<string> $supported_versions List of supported versions
     * @param string $default_version Default API version
     * @param string|null $base_namespace Custom base namespace
     * @return static New RestRoute instance
     */
    public static function create(
        Config|string $config_or_slug,
        array $supported_versions = ['v1'],
        string $default_version = 'v1',
        ?string $base_namespace = null
    ): static {
        return new static($config_or_slug, $supported_versions, $default_version, $base_namespace);
    }

    /**
     * Register a new API version.
     *
     * @param string $version Version identifier (e.g., 'v1', 'v2')
     * @param array<string, mixed> $config Version configuration
     * @return static For method chaining
     */
    public function registerVersion(string $version, array $config = []): static
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

        $this->versions[$version] = array_merge($defaults, $config);

        return $this;
    }

    /**
     * Deprecate an API version.
     *
     * @param string $version Version to deprecate
     * @param string $deprecation_date Deprecation date (ISO 8601)
     * @param string|null $removal_date Planned removal date
     * @param string|null $successor_version Recommended successor version
     * @return static For method chaining
     */
    public function deprecateVersion(
        string $version,
        string $deprecation_date,
        ?string $removal_date = null,
        ?string $successor_version = null
    ): static {
        if (!isset($this->versions[$version])) {
            throw new InvalidArgumentException("Version '{$version}' is not registered");
        }

        $this->versions[$version]['deprecated'] = true;
        $this->versions[$version]['deprecation_date'] = $deprecation_date;
        $this->versions[$version]['removal_date'] = $removal_date;
        $this->versions[$version]['successor_version'] = $successor_version;

        return $this;
    }

    /**
     * Add a route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param array<string, mixed> $config Route configuration
     * @return static For method chaining
     */
    public function addRoute(string $version, string $route, array $config): static
    {
        $version = sanitize_key($version);

        if (!isset($this->versions[$version])) {
            $this->registerVersion($version);
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
        $this->versions[$version]['routes'][$route] = array_merge($defaults, $config);

        return $this;
    }

    /**
     * Add multiple routes to a specific version.
     *
     * @param string $version API version
     * @param array<string, array<string, mixed>> $routes Routes to add
     * @return static For method chaining
     */
    public function addRoutes(string $version, array $routes): static
    {
        foreach ($routes as $route => $config) {
            $this->addRoute($version, $route, $config);
        }
        return $this;
    }

    /**
     * Add a GET route to a specific version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param callable $callback Route callback
     * @param array<string, mixed> $args Additional arguments
     * @return static For method chaining
     */
    public function get(string $version, string $route, callable $callback, array $args = []): static
    {
        return $this->addRoute($version, $route, array_merge($args, [
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
     * @return static For method chaining
     */
    public function post(string $version, string $route, callable $callback, array $args = []): static
    {
        return $this->addRoute($version, $route, array_merge($args, [
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
     * @return static For method chaining
     */
    public function put(string $version, string $route, callable $callback, array $args = []): static
    {
        return $this->addRoute($version, $route, array_merge($args, [
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
     * @return static For method chaining
     */
    public function delete(string $version, string $route, callable $callback, array $args = []): static
    {
        return $this->addRoute($version, $route, array_merge($args, [
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
     * @return static For method chaining
     */
    public function copyRoutes(string $from_version, string $to_version, array $exclude_routes = []): static
    {
        if (!isset($this->versions[$from_version])) {
            throw new InvalidArgumentException("Source version '{$from_version}' is not registered");
        }

        if (!isset($this->versions[$to_version])) {
            $this->registerVersion($to_version);
        }

        foreach ($this->versions[$from_version]['routes'] as $route => $config) {
            if (!in_array($route, $exclude_routes, true)) {
                $this->versions[$to_version]['routes'][$route] = $config;
            }
        }

        return $this;
    }

    /**
     * Add middleware to a specific version.
     *
     * @param string $version API version
     * @param callable $middleware Middleware function
     * @param int $priority Priority (lower = earlier)
     * @return static For method chaining
     */
    public function addMiddleware(string $version, callable $middleware, int $priority = 10): static
    {
        if (!isset($this->versions[$version])) {
            $this->registerVersion($version);
        }

        if (!isset($this->versions[$version]['middleware'][$priority])) {
            $this->versions[$version]['middleware'][$priority] = [];
        }

        $this->versions[$version]['middleware'][$priority][] = $middleware;
        ksort($this->versions[$version]['middleware']);

        return $this;
    }

    /**
     * Add global middleware applied to all versions.
     *
     * @param callable $middleware Middleware function
     * @param int $priority Priority (lower = earlier)
     * @return static For method chaining
     */
    public function addGlobalMiddleware(callable $middleware, int $priority = 10): static
    {
        if (!isset($this->global_middleware[$priority])) {
            $this->global_middleware[$priority] = [];
        }

        $this->global_middleware[$priority][] = $middleware;
        ksort($this->global_middleware);

        return $this;
    }

    /**
     * Get the full namespace for a specific version.
     *
     * @param string $version API version
     * @return string Full namespace
     */
    public function getFullNamespace(string $version): string
    {
        return $this->base_namespace . '/' . $version;
    }

    /**
     * Get the URL for a specific route and version.
     *
     * @param string $version API version
     * @param string $route Route pattern
     * @param array<string, mixed> $params URL parameters
     * @return string Route URL
     */
    public function getRouteUrl(string $version, string $route, array $params = []): string
    {
        $route = '/' . ltrim($route, '/');
        $namespace = $this->getFullNamespace($version);
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
    public function getAvailableVersions(bool $include_deprecated = true): array
    {
        if ($include_deprecated) {
            return array_keys($this->versions);
        }

        return array_keys(array_filter($this->versions, fn($config) => !$config['deprecated']));
    }

    /**
     * Get API documentation for all versions.
     *
     * @return array<string, mixed> API documentation
     */
    public function getApiDocumentation(): array
    {
        $docs = [
            'app_slug' => $this->app_slug,
            'base_namespace' => $this->base_namespace,
            'default_version' => $this->default_version,
            'versions' => [],
        ];

        foreach ($this->versions as $version => $config) {
            $version_docs = [
                'version' => $version,
                'namespace' => $this->getFullNamespace($version),
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
                    'url' => $this->getRouteUrl($version, $route),
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
    public function successResponse(mixed $data = null, string $message = '', int $status = 200): WP_REST_Response
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
    public function errorResponse(string $code, string $message, mixed $data = null, int $status = 400): WP_Error
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
    public function checkPermissions(string $capability = 'read')
    {
        if (!current_user_can($capability)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this resource', $this->text_domain),
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
    public function verifyNonce(WP_REST_Request $request, string $action, string $param = '_wpnonce')
    {
        $nonce = $request->get_param($param);

        if (!wp_verify_nonce($nonce, $action)) {
            return new WP_Error(
                'rest_nonce_invalid',
                __('Invalid nonce', $this->text_domain),
                ['status' => 403]
            );
        }

        return true;
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
     * Get the base namespace.
     *
     * @return string Base namespace
     */
    public function getBaseNamespace(): string
    {
        return $this->base_namespace;
    }

    /**
     * Get the default version.
     *
     * @return string Default version
     */
    public function getDefaultVersion(): string
    {
        return $this->default_version;
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

        add_action('rest_api_init', [$this, 'registerAllRoutes']);
        add_action('wp_head', [$this, 'addApiDiscovery']);

        $this->hooks_registered = true;
    }

    /**
     * Register all routes for all versions with WordPress.
     *
     * @return void
     */
    public function registerAllRoutes(): void
    {
        foreach ($this->versions as $version => $version_config) {
            $namespace = $this->getFullNamespace($version);

            foreach ($version_config['routes'] as $route => $config) {
                $route_config = [
                    'methods' => $config['methods'],
                    'callback' => [$this, 'handleRequest'],
                    'permission_callback' => $config['permission_callback'],
                    'args' => $this->buildRouteArgs($config['args']),
                ];

                // Store metadata for the handler
                $route_config['route_meta'] = [
                    'version' => $version,
                    'original_config' => $config,
                    'version_config' => $version_config,
                    'app_slug' => $this->app_slug,
                    'instance' => $this,
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
    public function handleRequest(WP_REST_Request $request)
    {
        try {
            // Extract version and route information
            $route_meta = $this->extractRouteMetadata($request);
            if (!$route_meta) {
                return new WP_Error(
                    'route_not_found',
                    __('Route not found', $this->text_domain),
                    ['status' => 404]
                );
            }

            $version = $route_meta['version'];
            $config = $route_meta['original_config'];
            $version_config = $route_meta['version_config'];

            // Check if version is deprecated
            $deprecation_response = $this->handleDeprecation($version, $version_config);
            if ($deprecation_response instanceof WP_Error) {
                return $deprecation_response;
            }

            // Apply global middleware
            $global_middleware_response = $this->applyGlobalMiddleware($request, $route_meta);
            if ($global_middleware_response instanceof WP_Error || $global_middleware_response instanceof WP_REST_Response) {
                return $global_middleware_response;
            }

            // Apply version-specific middleware
            $version_middleware_response = $this->applyVersionMiddleware($request, $route_meta);
            if ($version_middleware_response instanceof WP_Error || $version_middleware_response instanceof WP_REST_Response) {
                return $version_middleware_response;
            }

            // Validate request
            $validation_error = $this->validateRequest($request, $config);
            if ($validation_error) {
                return $validation_error;
            }

            // Sanitize request parameters
            $sanitized_request = $this->sanitizeRequest($request, $config);

            // Call the route callback
            $callback = $config['callback'];
            if (!is_callable($callback)) {
                return new WP_Error(
                    'invalid_callback',
                    __('Invalid route callback', $this->text_domain),
                    ['status' => 500]
                );
            }

            $response = call_user_func($callback, $sanitized_request);

            // Format and enhance response with version metadata
            $formatted_response = $this->formatResponse($response);

            if ($formatted_response instanceof WP_REST_Response) {
                // Add version headers
                $formatted_response->header('X-API-Version', $version);
                $formatted_response->header('X-API-App', $this->app_slug);

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
     * Add API discovery to HTML head.
     *
     * @return void
     */
    public function addApiDiscovery(): void
    {
        $default_namespace = $this->getFullNamespace($this->default_version);
        $api_url = rest_url($default_namespace);

        printf(
            '<link rel="https://api.w.org/" href="%s" data-app="%s" />',
            esc_url($api_url),
            esc_attr($this->app_slug)
        );
    }

    /**
     * Parse config or slug parameter and set instance properties.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @return void
     * @throws InvalidArgumentException If parameters are invalid
     */
    protected function parseConfigOrSlug(Config|string $config_or_slug): void
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
            throw new InvalidArgumentException('First parameter must be Config instance or string');
        }

        if (empty($this->app_slug)) {
            throw new InvalidArgumentException('App slug cannot be empty');
        }
    }

    /**
     * Extract route metadata from request.
     *
     * @param WP_REST_Request $request Request object
     * @return array<string, mixed>|null Route metadata
     */
    protected function extractRouteMetadata(WP_REST_Request $request): ?array
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
    protected function handleDeprecation(string $version, array $version_config)
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
                    __('API version %s has been removed. Please use version %s.', $this->text_domain),
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
    protected function applyGlobalMiddleware(WP_REST_Request $request, array $route_meta)
    {
        foreach ($this->global_middleware as $priority_group) {
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
    protected function applyVersionMiddleware(WP_REST_Request $request, array $route_meta)
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
    protected function validateRequest(WP_REST_Request $request, array $config): ?WP_Error
    {
        $args = $config['args'] ?? [];

        foreach ($args as $param => $param_config) {
            $value = $request->get_param($param);

            // Check required parameters
            if (($param_config['required'] ?? false) && $value === null) {
                return new WP_Error(
                    'missing_parameter',
                    sprintf(__('Missing required parameter: %s', $this->text_domain), $param),
                    ['status' => 400]
                );
            }

            // Custom validation
            if (isset($param_config['validate_callback']) && is_callable($param_config['validate_callback'])) {
                $is_valid = call_user_func($param_config['validate_callback'], $value, $request, $param);

                if (!$is_valid) {
                    return new WP_Error(
                        'invalid_parameter',
                        sprintf(__('Invalid parameter: %s', $this->text_domain), $param),
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
    protected function sanitizeRequest(WP_REST_Request $request, array $config): WP_REST_Request
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
    protected function formatResponse(mixed $response)
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
    protected function buildRouteArgs(array $args): array
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
