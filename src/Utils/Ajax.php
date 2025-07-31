<?php

/**
 * Ajax Handler
 *
 * @author Chibueze Aniezeofor <hello@codad5.me>
 * @license GPL-2.0-or-later http://www.gnu.org/licenses/gpl-2.0.txt
 * @link https://github.com/codad5/wptoolkit
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Utils;

use WP_Error;
use InvalidArgumentException;
use Exception;

/**
 * Enhanced Ajax Handler class for managing WordPress AJAX endpoints.
 *
 * Provides a clean API for creating AJAX endpoints with automatic nonce handling,
 * validation, middleware support, and comprehensive error handling.
 * Follows the same patterns as Page and RestRoute utilities.
 */
final class Ajax
{
    /**
     * Registered AJAX actions.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $actions = [];

    /**
     * Global middleware applied to all actions.
     *
     * @var array<int, array<callable>>
     */
    protected array $global_middleware = [];

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
     * Default nonce action prefix.
     */
    protected string $nonce_prefix;

    /**
     * Whether to automatically create nonces for actions.
     */
    protected bool $auto_nonce = true;

    /**
     * Rate limiting configuration.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $rate_limits = [];

    /**
     * Constructor for creating a new Ajax instance.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param bool $auto_nonce Whether to automatically handle nonces
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(Config|string $config_or_slug, bool $auto_nonce = true)
    {
        $this->parseConfigOrSlug($config_or_slug);
        $this->auto_nonce = $auto_nonce;
        $this->nonce_prefix = $this->app_slug . '_ajax_nonce';
        $this->registerHooks();
    }

    /**
     * Static factory method for creating Ajax instances.
     *
     * @param Config|string $config_or_slug Config instance or app slug
     * @param bool $auto_nonce Whether to automatically handle nonces
     * @return static New Ajax instance
     */
    public static function create(Config|string $config_or_slug, bool $auto_nonce = true): static
    {
        return new static($config_or_slug, $auto_nonce);
    }

    /**
     * Register an AJAX action.
     *
     * @param string $action Action name (without wp_ajax_ prefix)
     * @param callable $callback Action callback
     * @param array<string, mixed> $config Action configuration
     * @return static For method chaining
     */
    public function addAction(string $action, callable $callback, array $config = []): static
    {
        $action = sanitize_key($action);

        $defaults = [
            'callback' => $callback,
            'public' => true, // Whether to register for non-logged-in users
            'logged_in_only' => false, // Whether to require login
            'capability' => null, // Required capability
            'nonce_action' => $this->auto_nonce ? $this->nonce_prefix . '_' . $action : null,
            'validate_nonce' => $this->auto_nonce,
            'rate_limit' => null, // Rate limit config
            'middleware' => [], // Action-specific middleware
            'sanitize_callback' => null, // Custom sanitization
            'validate_callback' => null, // Custom validation
            'args' => [], // Expected parameters with validation rules
            'description' => '', // For documentation
            'deprecated' => false,
            'deprecation_message' => '',
        ];

        $this->actions[$action] = array_merge($defaults, $config);

        return $this;
    }

    /**
     * Register multiple AJAX actions at once.
     *
     * @param array<string, array<string, mixed>> $actions Actions to register
     * @return static For method chaining
     */
    public function addActions(array $actions): static
    {
        foreach ($actions as $action => $config) {
            $callback = $config['callback'] ?? null;
            if (!$callback) {
                throw new InvalidArgumentException("Callback is required for action: {$action}");
            }

            unset($config['callback']);
            $this->addAction($action, $callback, $config);
        }
        return $this;
    }

    /**
     * Add global middleware applied to all actions.
     *
     * @param callable $middleware Middleware function
     * @param int $priority Priority (lower = earlier)
     * @return static For method chaining
     */
    public function addMiddleware(callable $middleware, int $priority = 10): static
    {
        if (!isset($this->global_middleware[$priority])) {
            $this->global_middleware[$priority] = [];
        }

        $this->global_middleware[$priority][] = $middleware;
        ksort($this->global_middleware);

        return $this;
    }

    /**
     * Add rate limiting to an action.
     *
     * @param string $action Action name
     * @param int $max_requests Maximum requests
     * @param int $window_seconds Time window in seconds
     * @param string $identifier Rate limit identifier (ip, user_id, or custom)
     * @return static For method chaining
     */
    public function addRateLimit(
        string $action,
        int $max_requests,
        int $window_seconds,
        string $identifier = 'ip'
    ): static {
        $this->rate_limits[$action] = [
            'max_requests' => $max_requests,
            'window_seconds' => $window_seconds,
            'identifier' => $identifier,
        ];

        return $this;
    }

    /**
     * Get nonce for a specific action.
     *
     * @param string $action Action name
     * @return string Nonce value
     */
    public function getNonce(string $action): string
    {
        if (!isset($this->actions[$action])) {
            throw new InvalidArgumentException("Action '{$action}' is not registered");
        }

        $nonce_action = $this->actions[$action]['nonce_action'];
        return $nonce_action ? wp_create_nonce($nonce_action) : '';
    }

    /**
     * Get AJAX URL for frontend use.
     *
     * @return string AJAX URL
     */
    public function getAjaxUrl(): string
    {
        return admin_url('admin-ajax.php');
    }

	// Find this method and update the script_url line

	/**
	 * @throws Exception
	 */
	public function getJavaScriptData(array $actions = []): array
	{
		$actions_to_include = empty($actions) ? array_keys($this->actions) : $actions;
		$nonces = [];

		foreach ($actions_to_include as $action) {
			if (isset($this->actions[$action]) && $this->actions[$action]['nonce_action']) {
				$nonces[$action] = $this->getNonce($action);
			}
		}

		return [
			'ajax_url' => $this->getAjaxUrl(),
			'nonces' => $nonces,
			'app_slug' => $this->app_slug,
			'actions' => $actions_to_include,
			'script_url' => self::getAjaxHelperScriptUrl(), // Change this line
		];
	}

    /**
     * Create a success response.
     *
     * @param mixed $data Response data
     * @param string $message Success message
     * @return never
     */
    public function success(mixed $data = null, string $message = '')
    {
        $response = ['success' => true];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($message)) {
            $response['message'] = $message;
        }

        wp_send_json_success($response);
    }

    /**
     * Create an error response.
     *
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @param int $code Error code
     * @return never
     */
    public function error(string $message, mixed $data = null, int $code = 400)
    {
        $response = [
            'error' => true,
            'message' => $message,
            'code' => $code,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        wp_send_json_error($response);
    }

    /**
     * Handle AJAX requests with comprehensive processing.
     *
     * @param string $action Action name
     * @return void
     */
    public function handleRequest(string $action): void
    {
        try {
            if (!isset($this->actions[$action])) {
                $this->error('Action not found', null, 404);
            }

            $config = $this->actions[$action];

            // Check if action is deprecated
            if ($config['deprecated']) {
                $message = $config['deprecation_message'] ?: "Action '{$action}' is deprecated";
                header('Warning: 299 - "' . $message . '"');
            }

            // Check rate limiting
            if (isset($this->rate_limits[$action])) {
                $rate_limit_check = $this->checkRateLimit($action);
                if ($rate_limit_check instanceof WP_Error) {
                    $this->error($rate_limit_check->get_error_message(), null, 429);
                }
            }

            // Check authentication
            if ($config['logged_in_only'] && !is_user_logged_in()) {
                $this->error(__('Authentication required', $this->text_domain), null, 401);
            }

            // Check capability
            if ($config['capability'] && !current_user_can($config['capability'])) {
                $this->error(__('Insufficient permissions', $this->text_domain), null, 403);
            }

            // Validate nonce
            if ($config['validate_nonce'] && $config['nonce_action']) {
                if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', $config['nonce_action'])) {
                    $this->error(__('Invalid nonce', $this->text_domain), null, 403);
                }
            }

            // Apply global middleware
            $this->applyGlobalMiddleware($action, $config);

            // Apply action-specific middleware
            $this->applyActionMiddleware($action, $config);

            // Validate request data
            $validation_error = $this->validateRequest($config);
            if ($validation_error) {
                $this->error($validation_error->get_error_message(), $validation_error->get_error_data());
            }

            // Sanitize request data
            $sanitized_data = $this->sanitizeRequest($config);

            // Call the action callback
            call_user_func($config['callback'], $sanitized_data, $action, $this);
        } catch (Exception $e) {
            $this->error('Internal server error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get all registered actions.
     *
     * @return array<string> Action names
     */
    public function getRegisteredActions(): array
    {
        return array_keys($this->actions);
    }

    /**
     * Get action configuration.
     *
     * @param string $action Action name
     * @return array<string, mixed>|null Action config or null if not found
     */
    public function getActionConfig(string $action): ?array
    {
        return $this->actions[$action] ?? null;
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

        add_action('wp_ajax_' . $this->app_slug . '_ajax', [$this, 'routeRequest']);
        add_action('wp_ajax_nopriv_' . $this->app_slug . '_ajax', [$this, 'routeRequest']);

        // Register individual action hooks
        add_action('init', [$this, 'registerActionHooks'], 20);

        $this->hooks_registered = true;
    }

    /**
     * Register individual action hooks.
     *
     * @return void
     */
    public function registerActionHooks(): void
    {
        foreach ($this->actions as $action => $config) {
            $full_action = $this->app_slug . '_' . $action;

            // Register for logged-in users
            add_action('wp_ajax_' . $full_action, function () use ($action) {
                $this->handleRequest($action);
            });

            // Register for non-logged-in users if public
            if ($config['public']) {
                add_action('wp_ajax_nopriv_' . $full_action, function () use ($action) {
                    $this->handleRequest($action);
                });
            }
        }
    }

    /**
     * Route requests to the main handler (fallback).
     *
     * @return void
     */
    public function routeRequest(): void
    {
        $action = sanitize_key($_POST['sub_action'] ?? '');

        if (empty($action)) {
            $this->error('No sub-action specified');
        }

        $this->handleRequest($action);
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
     * Check rate limiting for an action.
     *
     * @param string $action Action name
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    protected function checkRateLimit(string $action)
    {
        $config = $this->rate_limits[$action];
        $identifier = $this->getRateLimitIdentifier($config['identifier']);

        $cache_key = "rate_limit_{$this->app_slug}_{$action}_{$identifier}";
        $requests = (int) wp_cache_get($cache_key, 'ajax_rate_limits') ?: 0;

        if ($requests >= $config['max_requests']) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Maximum %d requests per %d seconds.', $this->text_domain),
                    $config['max_requests'],
                    $config['window_seconds']
                )
            );
        }

        // Increment counter
        wp_cache_set($cache_key, $requests + 1, 'ajax_rate_limits', $config['window_seconds']);

        return true;
    }

    /**
     * Get rate limit identifier.
     *
     * @param string $type Identifier type
     * @return string Identifier value
     */
    protected function getRateLimitIdentifier(string $type): string
    {
        switch ($type) {
            case 'user_id':
                return (string) get_current_user_id();
            case 'ip':
            default:
                return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }

    /**
     * Apply global middleware.
     *
     * @param string $action Action name
     * @param array<string, mixed> $config Action configuration
     * @return void
     */
    protected function applyGlobalMiddleware(string $action, array $config): void
    {
        foreach ($this->global_middleware as $priority_group) {
            foreach ($priority_group as $middleware) {
                $result = call_user_func($middleware, $action, $config, $this);

                if ($result instanceof WP_Error) {
                    $this->error($result->get_error_message(), $result->get_error_data());
                }
            }
        }
    }

    /**
     * Apply action-specific middleware.
     *
     * @param string $action Action name
     * @param array<string, mixed> $config Action configuration
     * @return void
     */
    protected function applyActionMiddleware(string $action, array $config): void
    {
        foreach ($config['middleware'] as $middleware) {
            if (!is_callable($middleware)) {
                continue;
            }

            $result = call_user_func($middleware, $action, $config, $this);

            if ($result instanceof WP_Error) {
                $this->error($result->get_error_message(), $result->get_error_data());
            }
        }
    }

    /**
     * Validate request data.
     *
     * @param array<string, mixed> $config Action configuration
     * @return WP_Error|null Validation error or null if valid
     */
    protected function validateRequest(array $config): ?WP_Error
    {
        // Custom validation callback
        if ($config['validate_callback'] && is_callable($config['validate_callback'])) {
            $result = call_user_func($config['validate_callback'], $_POST, $config);

            if ($result instanceof WP_Error) {
                return $result;
            }

            if ($result === false) {
                return new WP_Error('validation_failed', 'Request validation failed');
            }
        }

        // Validate expected arguments
        foreach ($config['args'] as $arg => $arg_config) {
            $value = $_POST[$arg] ?? null;

            // Check required arguments
            if (($arg_config['required'] ?? false) && $value === null) {
                return new WP_Error(
                    'missing_argument',
                    sprintf(__('Missing required argument: %s', $this->text_domain), $arg)
                );
            }

            // Type validation
            if ($value !== null && isset($arg_config['type'])) {
                if (!$this->validateArgumentType($value, $arg_config['type'])) {
                    return new WP_Error(
                        'invalid_argument_type',
                        sprintf(__('Invalid type for argument %s. Expected %s.', $this->text_domain), $arg, $arg_config['type'])
                    );
                }
            }
        }

        return null;
    }

    /**
     * Sanitize request data.
     *
     * @param array<string, mixed> $config Action configuration
     * @return array<string, mixed> Sanitized data
     */
    protected function sanitizeRequest(array $config): array
    {
        $sanitized = $_POST;

        // Custom sanitization callback
        if ($config['sanitize_callback'] && is_callable($config['sanitize_callback'])) {
            $sanitized = call_user_func($config['sanitize_callback'], $sanitized, $config);
        }

        // Sanitize individual arguments
        foreach ($config['args'] as $arg => $arg_config) {
            if (isset($sanitized[$arg]) && isset($arg_config['sanitize_callback'])) {
                $sanitized[$arg] = call_user_func($arg_config['sanitize_callback'], $sanitized[$arg]);
            }
        }

        return $sanitized;
    }

    /**
     * Validate argument type.
     *
     * @param mixed $value Value to validate
     * @param string $type Expected type
     * @return bool Whether value matches type
     */
    protected function validateArgumentType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_numeric($value),
            'float', 'number' => is_numeric($value),
            'bool', 'boolean' => in_array($value, [true, false, 'true', 'false', '1', '0', 1, 0], true),
            'array' => is_array($value),
            'email' => is_email($value),
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => true,
        };
    }

	/**
	 * Get URL to the Ajax helper JavaScript file.
	 *
	 * @return string URL to ajax.js helper script
	 * @throws Exception
	 */
	final static function getAjaxHelperScriptUrl(): string
	{
		static $script_url = null;

		if ($script_url !== null) {
			return $script_url;
		}

		// Get current file path (e.g., C:/laragon/www/wp-content/plugins/my-plugin/src/Utils/Ajax.php)
		$current_file_path = __FILE__;


		// Find wp-content position and extract the relative path
		$wp_content_pos = strpos($current_file_path, 'wp-content');

		if ($wp_content_pos === false) {
			throw new Exception('Could not locate wp-content directory in path: ' . $current_file_path);
		}

		// Get the part from wp-content onwards (e.g., wp-content/plugins/my-plugin/src/Utils/Ajax.php)
		$relative_path = substr($current_file_path, $wp_content_pos);

		// Navigate from Ajax.php location to assets/js/ajax.js
		$path_parts = explode('/', dirname($relative_path));

		// Remove the last 2 parts (src/Utils) to get to root
		array_pop($path_parts); // Remove 'Utils'
		array_pop($path_parts); // Remove 'src'

		// Build the script path
		$script_relative_path = implode('/', $path_parts) . '/assets/js/wptoolkit-ajax.js';

		// Convert to full URL
		$script_url = site_url('/' . $script_relative_path);

		return $script_url;
	}

	/**
	 * Get the script handle for the Ajax helper script.
	 *
	 * @return string Script handle
	 */
	public static function getAjaxHelperScriptHandle(): string
	{
		return 'wptoolkit-ajax-helper';
	}

	/**
	 * Enqueue the Ajax helper script if not already enqueued.
	 *
	 * @param array $dependencies Script dependencies
	 * @param string|bool|null $version Script version
	 * @param bool $in_footer Whether to load in footer
	 * @return bool True if enqueued or already enqueued, false on error
	 */
	public static function enqueueAjaxHelperScript(
		array $dependencies = ['jquery'],
		string|bool|null $version = '1.0.0',
		bool $in_footer = true
	): bool {
		$handle = self::getAjaxHelperScriptHandle();

		// Check if already enqueued or registered
		if (wp_script_is($handle, 'enqueued') || wp_script_is($handle, 'registered')) {
			// If only registered, enqueue it
			if (!wp_script_is($handle, 'enqueued')) {
				wp_enqueue_script($handle);
			}
			return true;
		}

		try {
			$script_url = self::getAjaxHelperScriptUrl();

			wp_enqueue_script(
				$handle,
				$script_url,
				$dependencies,
				$version,
				$in_footer
			);

			return true;
		} catch (Exception $e) {
			// Log error if WP_DEBUG is enabled
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('WPToolkit Ajax Helper Script Error: ' . $e->getMessage());
			}
			return false;
		}
	}
}
