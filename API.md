# WPToolkit API Reference

## Table of Contents

### Core Framework
- [Registry](#registry) - Service container and dependency injection
- [Config](#config) - Immutable configuration management
- [Requirements](#requirements) - System requirements validation

### Database Layer (`Codad5\WPToolkit\DB`)
- [Model](#model) - Custom post type base class with enterprise features
- [MetaBox](#metabox) - Advanced custom fields framework

### Utilities (`Codad5\WPToolkit\Utils`)
- [Settings](#settings) - WordPress settings API wrapper
- [Page](#page) - Admin and frontend page management
- [Ajax](#ajax) - Secure AJAX handling with validation
- [Cache](#cache) - Multi-level caching system
- [EnqueueManager](#enqueuemanager) - Asset management and loading
- [Notification](#notification) - Admin notification system
- [Debugger](#debugger) - Development debugging utilities
- [Autoloader](#autoloader) - PSR-4 compliant class loading

---


---

## Configuration

*Management of plugin/theme configuration and lifecycle*

## Registry

*Centralized service container and dependency injection*

## Model

*Base model class for custom post types with enterprise features*

## Autoloader

*PSR-4 compliant autoloading for WordPress*

## Requirements

*System requirements validation and compatibility checking*

## MetaBox

*Type-safe custom fields framework with validation*

## Settings

*WordPress settings API abstraction with enhanced features*

## ViewLoader

*Template system with inheritance and caching*

## RestRoute

*Multi-version REST API framework*

## Cache

*Multi-level caching system with group management*

## Debugger

*Development and debugging utilities*

## Interfaces

*Core interfaces and contracts for extension*



## Registry

**Centralized service container and dependency injection system for managing application services across multiple WordPress applications.**

### Overview

The Registry class provides a static service container that allows you to:
- Register and manage multiple WordPress applications
- Store and retrieve service instances with dependency injection
- Support lazy loading through factories
- Create service aliases for easier access
- Manage application lifecycle and configuration

### Class Definition

```php
final class Registry
```

### Key Methods

#### Application Management

##### `registerApp(Config $config, array $services = []): bool`
Register a new application with optional initial services.

**Parameters:**
- `$config` (Config) - Application configuration instance
- `$services` (array) - Optional initial services to register

**Example:**
```php
// Register application with initial services
Registry::registerApp($config, [
    'mailer' => new MailService(),
    'cache' => new CacheService()
]);
```

##### `hasApp(string $app_slug): bool`
Check if an application is registered.

##### `removeApp(string $app_slug): bool`
Remove an entire application and all its services.

##### `getApps(): array`
Get all registered application slugs.

#### Service Management

##### `add(Config|string $config_or_slug, string $service_name, object $service): bool`
Add a single service to an application.

**Parameters:**
- `$config_or_slug` (Config|string) - Config instance or app slug
- `$service_name` (string) - Service identifier
- `$service` (object) - Service instance

**Example:**
```php
// Add service using config
Registry::add($config, 'payment', new PaymentGateway());

// Add service using app slug
Registry::add('my-plugin', 'analytics', new AnalyticsService());
```

##### `addMany(Config|string $config_or_slug, array $services): bool`
Add multiple services at once.

**Example:**
```php
Registry::addMany($config, [
    'mailer' => new MailService(),
    'payment' => new PaymentGateway(),
    'analytics' => new AnalyticsService()
]);
```

##### `get(string $app_slug, string $service_name): ?object`
Retrieve a service instance.

**Example:**
```php
$mailer = Registry::get('my-plugin', 'mailer');
$payment = Registry::get('my-plugin', 'payment');
```

##### `has(string $app_slug, string $service_name): bool`
Check if a service exists.

##### `getAll(string $app_slug): array`
Get all services for an application.

##### `remove(string $app_slug, string $service_name): bool`
Remove a specific service.

##### `update(string $app_slug, array $services): bool`
Update/replace existing services.

#### Factory & Lazy Loading

##### `factory(string $app_slug, string $service_name, callable $factory): bool`
Register a service factory for lazy loading.

**Example:**
```php
// Register factory that creates service when first accessed
Registry::factory('my-plugin', 'database', function(Config $config) {
    return new DatabaseService($config->get('db_settings'));
});
```

##### `factories(string $app_slug, array $factories): bool`
Register multiple factories.

**Example:**
```php
Registry::factories('my-plugin', [
    'heavy_service' => fn($config) => new HeavyService($config),
    'api_client' => fn($config) => new ApiClient($config->get('api_key'))
]);
```

#### Aliases

##### `alias(string $app_slug, string $alias, string $service_name): bool`
Create an alias for a service.

**Example:**
```php
// Create shortcuts for commonly used services
Registry::alias('my-plugin', 'mail', 'mailer');
Registry::alias('my-plugin', 'db', 'database');

// Now you can use the shorter alias
$mailer = Registry::get('my-plugin', 'mail');
```

##### `aliases(string $app_slug, array $aliases): bool`
Register multiple aliases.

#### Utility Methods

##### `getConfig(string $app_slug): ?Config`
Get application configuration.

##### `clear(string $app_slug): bool`
Clear all services for an application (keeps config).

##### `clearAll(): void`
Clear everything (use with caution).

##### `getStats(): array`
Get registry statistics for debugging.

##### `resolver(string $app_slug): callable`
Create a service resolver function.

**Example:**
```php
// Create resolver for dependency injection
$resolver = Registry::resolver('my-plugin');
$service = $resolver('mailer');
```

### Implementation Guide

#### Step 1: Basic Application Setup

Create your main app class and register it:

```php
// In your main plugin file
class MyApp {
    public static function init(Config $config): void {
        // Register the application
        Registry::registerApp($config);
        
        // Initialize core services
        self::initServices($config);
    }
    
    private static function initServices(Config $config): void {
        Registry::addMany($config, [
            'settings' => new SettingsService($config),
            'cache' => new CacheService(),
            'mailer' => new MailService()
        ]);
    }
}
```

#### Step 2: Service Access Pattern

Create a service accessor in your app class:

```php
class MyApp {
    // ... previous code ...
    
    public static function getService(string $service_name): ?object {
        return Registry::get(self::getConfig()->slug, $service_name);
    }
    
    // Type-safe accessors for common services
    public static function getMailer(): MailService {
        return self::getService('mailer');
    }
    
    public static function getCache(): CacheService {
        return self::getService('cache');
    }
}
```

#### Step 3: Lazy Loading for Heavy Services

For services that are expensive to create:

```php
// Register factories instead of instances
Registry::factories($config->slug, [
    'analytics' => function(Config $config) {
        return new AnalyticsService(
            $config->get('analytics_api_key'),
            $config->get('analytics_settings')
        );
    },
    'external_api' => function(Config $config) {
        return new ExternalApiClient($config->get('api_credentials'));
    }
]);
```

#### Step 4: Using Aliases for Convenience

```php
// Set up common aliases
Registry::aliases($config->slug, [
    'mail' => 'mailer',
    'db' => 'database',
    'api' => 'rest_route'
]);

// Usage becomes simpler
$mailer = Registry::get('my-plugin', 'mail'); // Instead of 'mailer'
```

#### Step 5: Service Dependencies

For services that depend on other services:

```php
// Register dependent services in correct order
Registry::add($config, 'config_service', new ConfigService($config));
Registry::add($config, 'database', new DatabaseService(
    Registry::get($config->slug, 'config_service')
));
```

### Best Practices

1. **Register Early**: Register your application in the `plugins_loaded` or `init` hook
2. **Use Factories**: For heavy services, use factories to avoid unnecessary loading
3. **Type Safety**: Create typed accessor methods in your app class
4. **Consistent Naming**: Use consistent service naming conventions
5. **Error Handling**: Always check if services exist before using them

### Error Handling

```php
// Safe service access
$mailer = Registry::get('my-plugin', 'mailer');
if ($mailer === null) {
    // Handle missing service
    error_log('Mailer service not available');
    return;
}

// Or use has() to check first
if (Registry::has('my-plugin', 'mailer')) {
    $mailer = Registry::get('my-plugin', 'mailer');
    $mailer->send($email);
}
```

### Debugging

Use the stats method to debug service registration:

```php
// In development mode
if (WP_DEBUG) {
    $stats = Registry::getStats();
    error_log('Registry Stats: ' . print_r($stats, true));
}
```

## Configuration

**Immutable configuration management system for WordPress applications with type safety, validation, and environment-aware features.**

### Overview

The Config class provides a robust, immutable configuration management system that:
- Validates and sanitizes configuration data
- Supports multiple creation patterns (plugin, theme, generic app)
- Provides environment detection (development, staging, production)
- Offers URL and path helpers for WordPress contexts
- Supports JSON serialization and environment variable loading
- Maintains immutability through fluent transformation methods

### Class Definition

```php
final class Config
{
    public readonly string $slug;
}
```

### Factory Methods

#### `Config::create(array $container): Config`
Generic factory method for creating configuration instances.

**Parameters:**
- `$container` (array) - Configuration data (must include 'slug' key)

**Example:**
```php
$config = Config::create([
    'slug' => 'my-app',
    'name' => 'My Application',
    'version' => '2.0.0',
    'debug' => true
]);
```

#### `Config::app(string $slug, string $name = '', string $version = '1.0.0'): Config`
Fluent factory for basic applications.

**Example:**
```php
// Minimal setup
$config = Config::app('my-plugin');

// With details
$config = Config::app('my-plugin', 'My Plugin Name', '1.2.0');
```

#### `Config::plugin(string $slug, string $file, array $additional = []): Config`
WordPress plugin-specific factory that extracts plugin header data.

**Parameters:**
- `$slug` (string) - Plugin slug
- `$file` (string) - Main plugin file path (usually `__FILE__`)
- `$additional` (array) - Additional configuration data

**Example:**
```php
// Basic plugin config
$config = Config::plugin('my-plugin', __FILE__);

// With additional settings
$config = Config::plugin('my-plugin', __FILE__, [
    'api_version' => 'v2',
    'cache_enabled' => true,
    'environment' => 'development'
]);
```

#### `Config::theme(string $slug, array $additional = []): Config`
WordPress theme-specific factory that extracts theme data.

**Example:**
```php
$config = Config::theme('my-theme', [
    'supports_customizer' => true,
    'child_theme_ready' => true
]);
```

### Data Access Methods

#### `get(string $name, mixed $default = null): mixed`
Retrieve configuration value with optional default.

**Example:**
```php
$debug = $config->get('debug', false);
$api_key = $config->get('api_key');
$timeout = $config->get('timeout', 30);
```

#### `has(string $name): bool`
Check if configuration key exists.

#### `all(): array`
Get all configuration data.

#### `keys(): array`
Get all configuration keys.

### Immutable Transformation Methods

#### `with(array $additional, bool $overwrite = true): Config`
Create new instance with additional data.

**Example:**
```php
// Add new settings
$prod_config = $config->with([
    'environment' => 'production',
    'debug' => false,
    'cache_ttl' => 3600
]);

// Merge without overwriting existing keys
$safe_config = $config->with($user_settings, false);
```

#### `without(array $keys): Config`
Create new instance excluding specified keys.

**Example:**
```php
// Remove sensitive data for public API
$public_config = $config->without(['api_key', 'db_password']);
```

#### `only(array $keys): Config`
Create new instance with only specified keys.

**Example:**
```php
// Extract only public information
$minimal_config = $config->only(['slug', 'name', 'version']);
```

### Environment Detection

#### `isDevelopment(): bool`
Check if running in development environment.

**Detection Logic:**
- `environment` key equals 'development'
- `debug` key is true
- WordPress `WP_DEBUG` constant is true

#### `isProduction(): bool`
Check if running in production environment.

#### `isStaging(): bool`
Check if running in staging environment.

**Example:**
```php
if ($config->isDevelopment()) {
    // Enable verbose logging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

if ($config->isProduction()) {
    // Optimize for performance
    wp_cache_flush();
}
```

### URL and Path Helpers

#### `url(string $path = ''): string`
Get application URL with optional path.

**Example:**
```php
// Base URL
$base_url = $config->url();

// Asset URL
$css_url = $config->url('assets/style.css');

// API endpoint URL
$api_url = $config->url('api/v1/users');
```

#### `path(string $path = ''): string`
Get application directory path with optional subpath.

**Example:**
```php
// Base directory
$plugin_dir = $config->path();

// Template file path
$template_path = $config->path('templates/email.php');

// Asset file path
$css_path = $config->path('assets/style.css');
```

### Serialization & Export

#### `toJson(int $flags = JSON_THROW_ON_ERROR): string`
Export configuration as JSON.

**Example:**
```php
// Standard JSON
$json = $config->toJson();

// Pretty printed JSON
$pretty_json = $config->toJson(JSON_PRETTY_PRINT);
```

#### `fromJson(string $json): Config`
Create configuration from JSON string.

**Example:**
```php
$json_config = '{"slug":"my-app","version":"1.0.0"}';
$config = Config::fromJson($json_config);
```

#### `format(string $format, array $exclude = []): string|array`
Export in specific format.

**Supported Formats:**
- `'array'` - PHP array
- `'json'` - JSON string
- `'env'` - Environment variable format

**Example:**
```php
// Export as environment variables
$env_vars = $config->format('env', ['sensitive_key']);

// Export as array excluding secrets
$safe_array = $config->format('array', ['api_key', 'password']);
```

### Environment Variable Support

#### `fromEnv(string $prefix, array $defaults = []): Config`
Create configuration from environment variables.

**Example:**
```php
// Load from environment with MYAPP_ prefix
$config = Config::fromEnv('MYAPP', [
    'slug' => 'my-app',
    'version' => '1.0.0'
]);

// Environment variables:
// MYAPP_DEBUG=true
// MYAPP_API_KEY=secret123
// MYAPP_TIMEOUT=30
```

### Magic Methods

The Config class supports property-style access:

```php
// Property access (read-only)
echo $config->slug;        // Same as $config->get('slug')
echo $config->version;     // Same as $config->get('version')

// Check existence
if (isset($config->api_key)) {
    // Key exists
}

// Attempting to set throws exception (immutable)
// $config->debug = true; // Throws exception
```

### Implementation Guide

#### Step 1: Basic Plugin Configuration

```php
// my-plugin.php
$config = Config::plugin('my-plugin', __FILE__, [
    'api_version' => 'v1',
    'cache_enabled' => true,
    'environment' => wp_get_environment_type()
]);
```

#### Step 2: Environment-Specific Settings

```php
// Apply environment-specific overrides
if ($config->isDevelopment()) {
    $config = $config->with([
        'debug' => true,
        'cache_enabled' => false,
        'log_level' => 'debug'
    ]);
} elseif ($config->isProduction()) {
    $config = $config->with([
        'debug' => false,
        'cache_enabled' => true,
        'log_level' => 'error'
    ]);
}
```

#### Step 3: Register with Registry

```php
// Register the application
Registry::registerApp($config);

// Make config accessible throughout app
class MyApp {
    private static Config $config;
    
    public static function init(Config $config): void {
        self::$config = $config;
    }
    
    public static function getConfig(): Config {
        return self::$config;
    }
}
```

#### Step 4: Use URL and Path Helpers

```php
// In your classes
class AssetManager {
    public function __construct(
        private Config $config
    ) {}
    
    public function getStyleUrl(): string {
        return $this->config->url('assets/style.css');
    }
    
    public function getTemplatePath(string $template): string {
        return $this->config->path("templates/{$template}.php");
    }
}
```

#### Step 5: Configuration Validation

```php
class ConfigValidator {
    public static function validate(Config $config): bool {
        $required = ['slug', 'name', 'version'];
        
        foreach ($required as $key) {
            if (!$config->has($key)) {
                throw new InvalidArgumentException("Missing required config: {$key}");
            }
        }
        
        return true;
    }
}
```

### Best Practices

1. **Use Factory Methods**: Use `plugin()`, `theme()`, or `app()` instead of direct instantiation
2. **Environment Configuration**: Set up environment-specific settings early
3. **Immutable Updates**: Use `with()`, `without()`, `only()` for configuration changes
4. **Path Helpers**: Use `url()` and `path()` methods for WordPress-aware URLs/paths
5. **Validation**: Validate required configuration keys after creation
6. **Registry Integration**: Register config with Registry for global access

### Common Patterns

#### Plugin with Environment Variables

```php
// Load base config from environment
$base_config = Config::fromEnv('MYPLUGIN', [
    'slug' => 'my-plugin',
    'name' => 'My Plugin'
]);

// Create plugin-specific config
$config = Config::plugin('my-plugin', __FILE__)
    ->with($base_config->all());
```

#### Development vs Production

```php
$config = Config::plugin('my-plugin', __FILE__);

// Apply environment-specific settings
$config = match(wp_get_environment_type()) {
    'development' => $config->with(['debug' => true, 'cache_ttl' => 0]),
    'staging' => $config->with(['debug' => false, 'cache_ttl' => 300]),
    'production' => $config->with(['debug' => false, 'cache_ttl' => 3600]),
    default => $config
};
```

#### Configuration Export for Frontend

```php
// Export safe config for JavaScript
$frontend_config = $config
    ->without(['api_key', 'db_password'])
    ->format('json');

wp_localize_script('my-app-js', 'MyAppConfig', json_decode($frontend_config, true));
```


## Ajax

**Enhanced AJAX handler for WordPress with automatic nonce handling, validation, middleware support, and JavaScript companion.**

### Overview

The Ajax class provides a robust AJAX management system that:
- Handles WordPress AJAX actions with automatic registration
- Validates and sanitizes request data with custom rules
- Supports middleware and rate limiting
- Includes comprehensive error handling
- Provides JavaScript companion class for frontend
- Manages nonces automatically
- Supports both logged-in and public endpoints

### Class Definition

```php
final class Ajax
```

### Factory Methods

#### `Ajax::create(Config|string $config_or_slug, bool $auto_nonce = true): Ajax`
Create new Ajax instance.

**Parameters:**
- `$config_or_slug` (Config|string) - Config instance or app slug
- `$auto_nonce` (bool) - Whether to automatically handle nonces

### Action Management

#### `addAction(string $action, callable $callback, array $config = []): Ajax`
Register an AJAX action with configuration.

**Configuration Options:**
- `public` (bool) - Allow non-logged-in users (default: true)
- `logged_in_only` (bool) - Require user login (default: false)
- `capability` (string) - Required user capability
- `validate_nonce` (bool) - Enable nonce validation (default: auto_nonce setting)
- `rate_limit` (array) - Rate limiting configuration
- `middleware` (array) - Action-specific middleware
- `args` (array) - Expected parameters with validation rules

**Example:**
```php
$ajax = Ajax::create($config);

$ajax->addAction('save_data', [$controller, 'saveData'], [
    'logged_in_only' => true,
    'capability' => 'edit_posts',
    'args' => [
        'title' => ['required' => true, 'type' => 'string'],
        'content' => ['required' => true, 'sanitize_callback' => 'wp_kses_post']
    ]
]);
```

#### `addActions(array $actions): Ajax`
Register multiple actions at once.

#### `addMiddleware(callable $middleware, int $priority = 10): Ajax`
Add global middleware applied to all actions.

#### `addRateLimit(string $action, int $max_requests, int $window_seconds, string $identifier = 'ip'): Ajax`
Add rate limiting to specific action.

**Example:**
```php
// Limit to 10 requests per minute per IP
$ajax->addRateLimit('contact_form', 10, 60, 'ip');
```

### Response Methods

#### `success(mixed $data = null, string $message = ''): never`
Send success response and terminate execution.

#### `error(string $message, mixed $data = null, int $code = 400): never`
Send error response and terminate execution.

### Utility Methods

#### `getNonce(string $action): string`
Get nonce for specific action.

#### `getAjaxUrl(): string`
Get WordPress AJAX URL.

#### `getJavaScriptData(array $actions = []): array`
Get configuration data for JavaScript companion.

**Example:**
```php
// Localize script with AJAX data
wp_localize_script('my-script', 'ajaxConfig', $ajax->getJavaScriptData());
```

#### `enqueueAjaxHelperScript(array $dependencies = ['jquery']): bool`
Enqueue the JavaScript companion script.

### Implementation Guide

#### Step 1: Create Ajax Instance

```php
class MyApp {
    private static Ajax $ajax;
    
    public static function init(Config $config): void {
        self::$ajax = Ajax::create($config);
        self::setupAjaxActions();
    }
    
    private static function setupAjaxActions(): void {
        self::$ajax
            ->addAction('get_posts', [self::class, 'handleGetPosts'], [
                'public' => true,
                'args' => ['page' => ['type' => 'int', 'default' => 1]]
            ])
            ->addAction('save_post', [self::class, 'handleSavePost'], [
                'logged_in_only' => true,
                'capability' => 'edit_posts'
            ]);
    }
}
```

#### Step 2: Create Action Handlers

```php
public static function handleGetPosts(array $data, string $action, Ajax $ajax): void {
    $posts = get_posts(['paged' => $data['page'] ?? 1]);
    $ajax->success($posts);
}

public static function handleSavePost(array $data, string $action, Ajax $ajax): void {
    $post_id = wp_insert_post($data);
    if (is_wp_error($post_id)) {
        $ajax->error($post_id->get_error_message());
    }
    $ajax->success(['post_id' => $post_id]);
}
```

#### Step 3: Enqueue JavaScript

```php
add_action('wp_enqueue_scripts', function() use ($ajax) {
    Ajax::enqueueAjaxHelperScript();
    wp_localize_script('wptoolkit-ajax-helper', 'ajaxConfig', 
        $ajax->getJavaScriptData()
    );
});
```

#### Step 4: Use JavaScript Companion

```javascript
// Initialize Ajax helper
const ajax = new WPToolkitAjax(ajaxConfig);

// Send request
ajax.post('get_posts', { page: 2 })
    .then(posts => console.log(posts))
    .catch(error => console.error(error));

// Upload file
const formData = new FormData(document.getElementById('upload-form'));
ajax.upload('upload_file', formData)
    .then(response => console.log('Uploaded:', response));
```

### JavaScript Companion (WPToolkitAjax)

#### Constructor

```javascript
const ajax = new WPToolkitAjax(config, options);
```

#### Core Methods

##### `send(action, data = {}, options = {}): Promise`
Send AJAX request with full configuration.

##### `post(action, data = {}, options = {}): Promise`
Send POST request.

##### `get(action, data = {}, options = {}): Promise`
Send GET-style request.

##### `upload(action, formData, options = {}): Promise`
Upload files via AJAX.

#### Advanced Methods

##### `parallel(requests): Promise`
Send multiple requests in parallel.

##### `sequence(requests): Promise`
Send multiple requests in sequence.

##### `createActionInstance(action, defaultData = {}): Object`
Create specialized instance for specific action.

**Example:**
```javascript
// Create specialized uploader
const uploader = ajax.createActionInstance('upload_file', {
    max_file_size: '5MB'
});

// Use it
uploader.upload(formData).then(response => {
    console.log('Upload complete:', response);
});
```

### Middleware System

#### Global Middleware

```php
$ajax->addMiddleware(function($action, $config, Ajax $ajax) {
    // Log all requests
    error_log("AJAX request: {$action}");
    
    // Return WP_Error to stop execution
    if ($some_condition) {
        return new WP_Error('blocked', 'Request blocked');
    }
    
    return true; // Continue processing
}, 5);
```

#### Action-Specific Middleware

```php
$ajax->addAction('sensitive_action', $callback, [
    'middleware' => [
        function($action, $config, Ajax $ajax) {
            // Additional validation
            if (!current_user_can('administrator')) {
                return new WP_Error('forbidden', 'Admin required');
            }
            return true;
        }
    ]
]);
```

### Validation & Sanitization

#### Parameter Validation

```php
$ajax->addAction('create_user', $callback, [
    'args' => [
        'email' => [
            'required' => true,
            'type' => 'email',
            'sanitize_callback' => 'sanitize_email'
        ],
        'age' => [
            'type' => 'int',
            'validate_callback' => fn($v) => $v >= 18 ?: 'Must be 18+'
        ]
    ]
]);
```

#### Custom Validation

```php
$ajax->addAction('complex_action', $callback, [
    'validate_callback' => function($data, $config) {
        if (empty($data['required_field'])) {
            return new WP_Error('missing_field', 'Required field missing');
        }
        return true;
    }
]);
```

### Error Handling

#### PHP Error Handling

```php
public static function handleAction(array $data, string $action, Ajax $ajax): void {
    try {
        // Your logic here
        $result = do_something($data);
        $ajax->success($result);
    } catch (Exception $e) {
        $ajax->error('Operation failed: ' . $e->getMessage());
    }
}
```

#### JavaScript Error Handling

```javascript
ajax.post('risky_action', data)
    .then(response => {
        // Handle success
    })
    .catch(error => {
        if (error.code === 'validation_failed') {
            // Handle validation error
        } else {
            // Handle other errors
        }
    });

// Global error handler
const ajax = new WPToolkitAjax(config, {
    globalErrorHandler: (error, action) => {
        console.error(`Ajax error in ${action}:`, error);
    }
});
```

### Best Practices

1. **Security First**: Always validate capabilities and nonces for sensitive actions
2. **Rate Limiting**: Apply rate limits to prevent abuse
3. **Error Handling**: Use try-catch blocks and meaningful error messages
4. **Middleware**: Use middleware for cross-cutting concerns like logging
5. **JavaScript**: Use the companion class for consistent frontend handling
6. **Performance**: Consider caching for expensive operations

## APIHelper

**Abstract base class for creating HTTP API clients with built-in caching, endpoint management, and WordPress integration.**

### Overview

The APIHelper class provides a robust foundation for building API clients that:
- Manages HTTP requests with automatic caching via Cache utility
- Supports predefined endpoint configurations with placeholders
- Tracks API call counts with automatic reset
- Handles JSON responses with error management
- Integrates seamlessly with WordPress HTTP API
- Provides cache management and statistics

### Class Definition

```php
abstract class APIHelper
```

### Required Abstract Methods

Child classes must implement these methods:

#### `get_endpoints(): array`
Define API endpoints configuration.

#### `get_slug(): string`
Return plugin/app slug for cache keys.

#### `get_base_url(): string`
Return base API URL.

#### `get_headers(): array`
Return default request headers.

### Core Request Methods

#### `request(string $method, string $url, array $params = [], array $args = []): mixed`
Make HTTP request with caching support.

**Parameters:**
- `$method` (string) - HTTP method (GET, POST, etc.)
- `$url` (string) - Request URL
- `$params` (array) - Request parameters
- `$args` (array) - Additional request arguments including cache settings

#### `make_request(string $endpoint_name, array $params = [], array $substitutions = []): mixed`
Make request using predefined endpoint configuration.

**Parameters:**
- `$endpoint_name` (string) - Predefined endpoint identifier
- `$params` (array) - Request parameters
- `$substitutions` (array) - URL placeholder substitutions

### Cache Management

#### `cache(string $key, mixed $value, ?int $expiration = null): bool`
Cache data with optional expiration.

#### `get_cache(string $key): mixed`
Retrieve cached data.

#### `remember(string $key, callable $callback, ?int $expiration = null): mixed`
Get from cache or compute and cache if not exists.

#### `clear_cache(): int`
Clear all cached data for this API helper.

#### `get_cache_stats(): array`
Get cache statistics.

### Utility Methods

#### `prepare_request_url(string $url, array $params = []): string`
Build URL with query parameters.

#### `get_api_call_count(): int`
Get total API calls made (auto-resets every 30 days).

#### `set_name(string $name): void`
Set helper name identifier.

#### `set_cache_duration(int $duration): void`
Set default cache duration in minutes.

### Implementation Guide

#### Step 1: Create Your API Helper Class

```php
class MyAPIHelper extends APIHelper
{
    protected static string $name = 'MyAPI';
    
    protected static function get_endpoints(): array
    {
        return [
            'get_user' => [
                'route' => '/users/{{id}}',
                'method' => 'GET',
                'cache' => 60 // minutes
            ],
            'create_post' => [
                'route' => '/posts',
                'method' => 'POST',
                'cache' => false
            ]
        ];
    }
    
    protected static function get_slug(): string
    {
        return 'my-plugin';
    }
    
    protected static function get_base_url(): string
    {
        return 'https://api.example.com/v1';
    }
    
    protected static function get_headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . get_option('my_api_token'),
            'Content-Type' => 'application/json'
        ];
    }
}
```

#### Step 2: Use Predefined Endpoints

```php
// Get user by ID (uses placeholder substitution)
$user = MyAPIHelper::make_request('get_user', [], ['id' => '123']);

// Create post with parameters
$post = MyAPIHelper::make_request('create_post', [
    'title' => 'New Post',
    'content' => 'Post content'
]);
```

#### Step 3: Direct HTTP Requests

```php
// Custom GET request with caching
$data = MyAPIHelper::request('GET', 'https://api.example.com/data', [], [
    'cache' => 30 // Cache for 30 minutes
]);

// POST request without caching
$result = MyAPIHelper::request('POST', 'https://api.example.com/submit', [
    'field' => 'value'
], ['cache' => false]);
```

#### Step 4: Cache Management

```php
// Remember pattern - compute if not cached
$expensive_data = MyAPIHelper::remember('expensive_operation', function() {
    return perform_expensive_calculation();
}, 120);

// Clear all API cache
MyAPIHelper::clear_cache();

// Get cache statistics
$stats = MyAPIHelper::get_cache_stats();
```

### Endpoint Configuration

Endpoints support these configuration options:

```php
'endpoint_name' => [
    'route' => '/path/{{placeholder}}',    // URL route with placeholders
    'method' => 'GET',                     // HTTP method
    'params' => ['default' => 'value'],    // Default parameters
    'headers' => ['Custom' => 'Header'],   // Additional headers
    'cache' => true|false|int|callable     // Cache configuration
]
```

### Cache Configuration Options

- `true` - Use default cache duration
- `false` - Disable caching
- `int` - Cache duration in minutes
- `callable` - Function that returns boolean based on response data

**Example:**
```php
'cache' => function($response_data) {
    return !isset($response_data['error']);
}
```

### Placeholder Substitution

Use `{{placeholder}}` in routes and provide substitutions:

```php
// Route: '/users/{{user_id}}/posts/{{post_id}}'
MyAPIHelper::make_request('get_user_post', [], [
    'user_id' => '123',
    'post_id' => '456'
]);
```

### Error Handling

```php
try {
    $data = MyAPIHelper::make_request('api_endpoint');
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    // Handle error appropriately
}
```

### Advanced Features

#### Custom Cache Logic

```php
// Cache only successful responses
protected static function get_endpoints(): array
{
    return [
        'get_data' => [
            'route' => '/data',
            'cache' => function($data) {
                return isset($data['success']) && $data['success'];
            }
        ]
    ];
}
```

#### Dynamic Headers

```php
protected static function get_headers(): array
{
    $headers = ['Content-Type' => 'application/json'];
    
    if ($token = get_option('api_token')) {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    
    return $headers;
}
```

#### API Call Monitoring

```php
// Check API usage
$call_count = MyAPIHelper::get_api_call_count();
if ($call_count > 1000) {
    // Warn about high usage
}
```

### Best Practices

1. **Caching Strategy**: Cache GET requests that don't change frequently
2. **Error Handling**: Always wrap API calls in try-catch blocks
3. **Rate Limiting**: Monitor API call counts to avoid hitting limits
4. **Security**: Store API keys securely using WordPress options
5. **Performance**: Use `remember()` for expensive operations
6. **Placeholders**: Use route placeholders for dynamic endpoints

## Autoloader

**PSR-4 compatible autoloader with namespace mapping, class file mapping, and WordPress plugin tracking.**

### Overview

The Autoloader class provides a robust autoloading system that:
- Supports PSR-4 namespace-to-directory mapping
- Manages direct class-to-file mappings
- Tracks multiple plugin registrations with conflict detection
- Generates class maps from directories
- Validates class existence in files
- Integrates seamlessly with WordPress plugins and themes

### Class Definition

```php
class Autoloader
```

### Core Methods

#### `init(array $namespace_map = [], array $class_map = [], bool $overwrite = false, ?string $plugin_id = null): bool`
Initialize and register autoloader with mappings.

**Parameters:**
- `$namespace_map` (array) - Namespace to directory mappings
- `$class_map` (array) - Class to file mappings
- `$overwrite` (bool) - Whether to overwrite existing mappings
- `$plugin_id` (string|null) - Plugin identifier for tracking

#### `register(bool $prepend = false): bool`
Register the autoloader with PHP's SPL autoload stack.

#### `add_namespace(string $namespace, string $base_dir, bool $overwrite = false, bool $prepend = false): bool`
Add PSR-4 namespace mapping.

**Example:**
```php
Autoloader::add_namespace('MyPlugin\\', __DIR__ . '/src/');
```

#### `add_class_map(string $class_name, string $file_path, bool $overwrite = false): bool`
Add direct class-to-file mapping.

### Namespace Management

#### `add_namespaces(array $namespaces, bool $overwrite = false): bool`
Add multiple namespace mappings.

#### `get_namespaces(): array`
Get all registered namespace mappings.

### Class Mapping

#### `add_class_maps(array $class_map, bool $overwrite = false): bool`
Add multiple class-to-file mappings.

#### `get_class_maps(): array`
Get all registered class mappings.

#### `generate_class_map(string $directory, string $namespace_prefix = '', bool $recursive = true): array`
Generate class map by scanning directory.

#### `load_class_map_from_directory(string $directory, string $namespace_prefix = '', bool $recursive = true): bool`
Generate and load class map from directory.

### Plugin Tracking

#### `remove_plugin_mappings(string $plugin_id): bool`
Remove all mappings for a specific plugin.

#### `get_initialized_plugins(): array`
Get information about all initialized plugins.

#### `is_plugin_initialized(string $plugin_id): bool`
Check if a plugin has been initialized.

### Utility Methods

#### `load_class(string $class_name): bool`
Load a class file (used by SPL autoloader).

#### `can_load_class(string $class_name): bool`
Check if a class can be loaded without actually loading it.

#### `get_loaded_classes(): array`
Get all classes that have been loaded.

#### `clear_cache(): void`
Clear the loaded classes cache.

### Implementation Guide

#### Step 1: Basic Plugin Setup

```php
// In your main plugin file
Autoloader::init([
    'MyPlugin\\' => __DIR__ . '/src/',
    'MyPlugin\\Admin\\' => __DIR__ . '/src/Admin/',
], [], false, 'my-plugin');
```

#### Step 2: Theme Integration

```php
// In functions.php
Autoloader::init([
    'MyTheme\\' => get_template_directory() . '/inc/',
], [], false, get_template());
```

#### Step 3: Advanced Configuration

```php
// Multiple namespaces with class mapping
Autoloader::init([
    'MyPlugin\\Core\\' => __DIR__ . '/core/',
    'MyPlugin\\Utils\\' => __DIR__ . '/utils/',
], [
    'MyPlugin\\SpecialClass' => __DIR__ . '/special/SpecialClass.php'
], false, 'my-plugin');
```

#### Step 4: Generate Class Map

```php
// Generate and load class map from directory
Autoloader::load_class_map_from_directory(
    __DIR__ . '/src/',
    'MyPlugin',
    true // recursive
);
```

### Conflict Detection

The autoloader detects and warns about conflicts:

```php
// First plugin
Autoloader::add_namespace('Shared\\', '/path1/');

// Second plugin - triggers warning
Autoloader::add_namespace('Shared\\', '/path2/'); // Warning logged
```

### Plugin Cleanup

```php
// When plugin is deactivated
register_deactivation_hook(__FILE__, function() {
    Autoloader::remove_plugin_mappings('my-plugin');
});
```

### Advanced Features

#### Custom File Extensions

```php
Autoloader::add_file_extensions(['.class.php', '.inc']);
```

#### Directory Scanning

```php
// Generate class map for existing codebase
$class_map = Autoloader::generate_class_map(
    __DIR__ . '/legacy/',
    'Legacy\\Namespace',
    true
);
```

#### Conditional Loading

```php
// Check before attempting to use
if (Autoloader::can_load_class('MyPlugin\\OptionalClass')) {
    $instance = new MyPlugin\OptionalClass();
}
```

### Debug Information

```php
// Get autoloader status and mappings
$info = Autoloader::dump_info();
var_dump($info['namespace_map']);
var_dump($info['loaded_classes']);
```

### WordPress Integration Patterns

#### Plugin Structure

```
my-plugin/
├── my-plugin.php
├── src/
│   ├── Core/
│   │   └── Plugin.php
│   ├── Admin/
│   │   └── Settings.php
│   └── Frontend/
│       └── Display.php
```

#### Autoloader Setup

```php
// my-plugin.php
Autoloader::init([
    'MyPlugin\\' => __DIR__ . '/src/'
], [], false, plugin_basename(__FILE__));

// Now classes auto-load
$plugin = new MyPlugin\Core\Plugin();
```

#### Multiple Plugin Support

```php
// Plugin A
Autoloader::init(['PluginA\\' => __DIR__ . '/src/'], [], false, 'plugin-a');

// Plugin B (different namespace, no conflict)
Autoloader::init(['PluginB\\' => __DIR__ . '/src/'], [], false, 'plugin-b');
```

### Performance Considerations

#### Class Map vs Namespace Mapping

- **Class Map**: Faster lookup, direct file mapping
- **Namespace Mapping**: More flexible, follows PSR-4 standard

#### Optimization Tips

```php
// Pre-generate class maps for production
if (!WP_DEBUG) {
    Autoloader::load_class_map_from_directory(__DIR__ . '/src/', 'MyPlugin');
} else {
    Autoloader::add_namespace('MyPlugin\\', __DIR__ . '/src/');
}
```

### Best Practices

1. **Unique Namespaces**: Use vendor/plugin-specific namespaces to avoid conflicts
2. **Plugin Tracking**: Always provide plugin_id for proper cleanup
3. **Directory Structure**: Follow PSR-4 conventions for predictable loading
4. **Conflict Handling**: Don't set overwrite=true unless necessary
5. **Performance**: Use class maps for large codebases in production
6. **Cleanup**: Remove mappings on plugin deactivation

## Cache

**WordPress transient management utility with group organization, bulk operations, and automatic key tracking.**

### Overview

The Cache class provides a clean interface for WordPress transient operations that:
- Manages WordPress transients with automatic key prefixing
- Organizes cache entries into groups for bulk operations
- Tracks cache keys for efficient group clearing
- Supports bulk set/get operations
- Provides cache statistics and monitoring
- Handles key length limitations automatically
- Implements the "remember" pattern for expensive operations

### Class Definition

```php
class Cache
```

### Core Methods

#### `set(string $key, mixed $value, ?int $expiration = null, string $group = 'default'): bool`
Store a value in cache.

**Parameters:**
- `$key` (string) - Cache key
- `$value` (mixed) - Value to cache
- `$expiration` (int|null) - Expiration in seconds (default: 3600)
- `$group` (string) - Cache group for organization

#### `get(string $key, mixed $default = false, string $group = 'default'): mixed`
Retrieve a cached value.

#### `delete(string $key, string $group = 'default'): bool`
Remove a specific cache entry.

#### `exists(string $key, string $group = 'default'): bool`
Check if a cache key exists.

### Advanced Methods

#### `remember(string $key, callable $callback, ?int $expiration = null, string $group = 'default'): mixed`
Get from cache or compute and cache if not exists.

**Example:**
```php
$posts = Cache::remember('recent_posts', function() {
    return get_posts(['numberposts' => 10]);
}, 1800, 'posts');
```

#### `set_many(array $items, ?int $expiration = null, string $group = 'default'): array`
Set multiple cache values at once.

#### `get_many(array $keys, mixed $default = false, string $group = 'default'): array`
Get multiple cache values at once.

### Group Management

#### `clear_group(string $group = 'default'): int`
Clear all cached data for a specific group.

**Example:**
```php
// Clear all user-related cache
Cache::clear_group('users');
```

#### `clear_all(): int`
Clear all WPToolkit cache entries across all groups.

#### `list_group(string $group = 'default'): array`
List all cached data for a specific group.

### Statistics & Monitoring

#### `get_stats(string $group = 'default'): array`
Get cache statistics for a group.

**Returns:**
- `count` - Number of cache entries
- `size_estimate` - Estimated size in bytes
- `keys` - Array of cache keys

#### `flush_expired(string $group = 'default'): int`
Remove expired cache entries and update tracking.

### Implementation Guide

#### Step 1: Basic Caching

```php
// Store data
Cache::set('user_settings', $settings, 3600, 'users');

// Retrieve data
$settings = Cache::get('user_settings', [], 'users');

// Check existence
if (Cache::exists('user_settings', 'users')) {
    // Handle cached data
}
```

#### Step 2: Expensive Operations

```php
// Cache expensive database queries
$popular_posts = Cache::remember('popular_posts', function() {
    // Complex query here
    return $wpdb->get_results($sql);
}, 7200, 'posts');
```

#### Step 3: Bulk Operations

```php
// Set multiple values
$user_data = [
    'user_123' => $user_info,
    'user_456' => $other_user_info
];
Cache::set_many($user_data, 1800, 'users');

// Get multiple values
$cached_users = Cache::get_many(['user_123', 'user_456'], null, 'users');
```

#### Step 4: Cache Management

```php
// Clear specific group
Cache::clear_group('posts');

// Get cache statistics
$stats = Cache::get_stats('users');
echo "Cached users: " . $stats['count'];

// Clean up expired entries
$removed = Cache::flush_expired('posts');
```

### Group Organization Patterns

#### By Data Type

```php
Cache::set('posts_recent', $posts, 1800, 'posts');
Cache::set('user_profile_123', $profile, 3600, 'users');
Cache::set('settings_theme', $settings, 7200, 'settings');
```

#### By Plugin/Feature

```php
Cache::set('api_response', $data, 900, 'my_plugin_api');
Cache::set('widget_data', $widget, 1800, 'my_plugin_widgets');
```

#### By Expiration Strategy

```php
Cache::set('short_term', $data, 300, 'temporary');    // 5 minutes
Cache::set('medium_term', $data, 3600, 'hourly');     // 1 hour
Cache::set('long_term', $data, 86400, 'daily');       // 24 hours
```

### WordPress Integration

#### Hook-Based Cache Invalidation

```php
// Clear cache when content changes
add_action('save_post', function($post_id) {
    Cache::delete("post_{$post_id}", 'posts');
    Cache::clear_group('post_lists');
});

add_action('user_profile_update_errors', function($user_id) {
    Cache::delete("user_{$user_id}", 'users');
});
```

#### Admin Cache Management

```php
// Add admin menu for cache management
add_action('admin_menu', function() {
    add_management_page(
        'Cache Management',
        'Cache',
        'manage_options',
        'cache-management',
        'render_cache_admin_page'
    );
});

function render_cache_admin_page() {
    $stats = Cache::get_stats('posts');
    echo "Posts cached: " . $stats['count'];
    
    if (isset($_POST['clear_cache'])) {
        $cleared = Cache::clear_group($_POST['group']);
        echo "Cleared {$cleared} entries";
    }
}
```

### Performance Optimization

#### Smart Caching Strategy

```php
class PostCache {
    public static function getPost($id) {
        return Cache::remember("post_{$id}", function() use ($id) {
            return get_post($id);
        }, 3600, 'posts');
    }
    
    public static function invalidatePost($id) {
        Cache::delete("post_{$id}", 'posts');
    }
}
```

#### Batch Operations

```php
// Efficient bulk caching
$post_ids = [1, 2, 3, 4, 5];
$cache_keys = array_map(fn($id) => "post_{$id}", $post_ids);
$cached_posts = Cache::get_many($cache_keys, null, 'posts');

// Cache misses
$missing_ids = [];
foreach ($post_ids as $id) {
    if ($cached_posts["post_{$id}"] === null) {
        $missing_ids[] = $id;
    }
}

// Fetch and cache missing posts
if (!empty($missing_ids)) {
    $posts = get_posts(['include' => $missing_ids]);
    $to_cache = [];
    foreach ($posts as $post) {
        $to_cache["post_{$post->ID}"] = $post;
    }
    Cache::set_many($to_cache, 3600, 'posts');
}
```

### Error Handling

```php
// Safe caching with fallbacks
function get_user_data($user_id) {
    try {
        return Cache::remember("user_{$user_id}", function() use ($user_id) {
            $user = get_userdata($user_id);
            if (!$user) {
                throw new Exception("User not found");
            }
            return $user;
        }, 1800, 'users');
    } catch (Exception $e) {
        error_log('Cache error: ' . $e->getMessage());
        return get_userdata($user_id); // Direct fallback
    }
}
```

### Best Practices

1. **Group Organization**: Use logical groups for related data
2. **Appropriate Expiration**: Set expiration based on data update frequency
3. **Cache Invalidation**: Clear cache when underlying data changes
4. **Bulk Operations**: Use `set_many`/`get_many` for efficiency
5. **Error Handling**: Always have fallbacks for cache failures
6. **Monitoring**: Use `get_stats()` to monitor cache usage
7. **Cleanup**: Regularly run `flush_expired()` to maintain performance

## Debugger

**Development debugging utility with console logging, variable inspection, breakpoints, and performance monitoring.**

### Overview

The Debugger class provides comprehensive debugging tools that:
- Logs messages to browser console with app identification
- Dumps variables with formatted output and caller information
- Creates execution breakpoints for debugging
- Tracks performance timing with millisecond precision
- Supports multiple app instances simultaneously
- Integrates with WordPress error logging
- Only activates in development mode for production safety

### Class Definition

```php
class Debugger
```

### Initialization

#### `init(string $app_slug, ?bool $is_dev = null, ?string $app_name = null, ?string $text_domain = null): bool`
Initialize debugger for specific application.

**Parameters:**
- `$app_slug` (string) - Application identifier
- `$is_dev` (bool|null) - Development mode (default: WP_DEBUG)
- `$app_name` (string|null) - Display name
- `$text_domain` (string|null) - Translation domain

#### `initFromConfig(Config $config, ?bool $is_dev = null): bool`
Initialize from Config instance.

**Example:**
```php
Debugger::initFromConfig($config);
```

### Console Logging

#### `console(string $app_slug, mixed $message, string $type = 'log', bool $include_trace = true): ?bool`
Log message to browser console.

**Types:** `log`, `info`, `warn`, `error`, `debug`

#### `info(string $app_slug, mixed $message, bool $include_trace = true): ?bool`
Log info message to console.

#### `warn(string $app_slug, mixed $message, bool $include_trace = true): ?bool`
Log warning message to console.

#### `error(string $app_slug, mixed $message, bool $include_trace = true): ?bool`
Log error message to console.

**Example:**
```php
Debugger::console('my-plugin', 'Debug message');
Debugger::error('my-plugin', 'Something went wrong');
```

### Variable Inspection

#### `printR(string $app_slug, mixed $data, bool|string $die = false): void`
Print formatted variable output with caller information.

#### `varDump(string $app_slug, mixed $data, bool|string $die = false): void`
Dump variable using var_dump with styled output.

**Example:**
```php
Debugger::printR('my-plugin', $complex_array);
Debugger::varDump('my-plugin', $object, 'Debug complete');
```

### Breakpoints & Control

#### `breakpoint(string $app_slug, string $message = '', array $context = []): void`
Create execution breakpoint with context display.

**Example:**
```php
Debugger::breakpoint('my-plugin', 'Checking user data', ['user_id' => $id]);
```

#### `sleep(string $app_slug, int $seconds): void`
Sleep for specified seconds (development mode only).

### Performance Monitoring

#### `timer(string $app_slug, string $operation, ?float $start_time = null): float`
Log performance timing information.

**Example:**
```php
$start = Debugger::timer('my-plugin', 'Database query');
// ... expensive operation ...
Debugger::timer('my-plugin', 'Database query', $start);
```

### Logging & Utilities

#### `log(string $app_slug, mixed $message, string $level = 'DEBUG'): void`
Write message to WordPress error log.

#### `queryInfo(string $app_slug): void`
Dump current WordPress query information.

#### `notification(string $app_slug, string $message, string $type = 'info'): bool`
Add debug notification (requires Notification helper).

### Development Detection

#### `isDev(string $app_slug): bool`
Check if app is in development mode.

### Implementation Guide

#### Step 1: Initialize Debugger

```php
// Basic initialization
Debugger::init('my-plugin');

// With config
Debugger::initFromConfig($config);

// Force development mode
Debugger::init('my-plugin', true, 'My Plugin');
```

#### Step 2: Console Logging

```php
// Various log levels
Debugger::info('my-plugin', 'User logged in');
Debugger::warn('my-plugin', 'Performance warning');
Debugger::error('my-plugin', 'API call failed');

// Complex data
Debugger::console('my-plugin', ['user' => $user_data, 'settings' => $settings]);
```

#### Step 3: Variable Inspection

```php
// Quick variable check
Debugger::printR('my-plugin', $post_data);

// Detailed inspection with stop
Debugger::varDump('my-plugin', $wp_query, 'Query analysis complete');
```

#### Step 4: Performance Tracking

```php
function expensive_operation() {
    $start = Debugger::timer('my-plugin', 'Complex calculation');
    
    // ... your code ...
    
    Debugger::timer('my-plugin', 'Complex calculation', $start);
}
```

### Development Patterns

#### Conditional Debugging

```php
if (Debugger::isDev('my-plugin')) {
    // Development-only code
    Debugger::queryInfo('my-plugin');
}
```

#### Error Context

```php
try {
    risky_operation();
} catch (Exception $e) {
    Debugger::error('my-plugin', $e->getMessage());
    Debugger::printR('my-plugin', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'context' => $current_context
    ]);
}
```

#### Hook Debugging

```php
add_action('save_post', function($post_id) {
    Debugger::info('my-plugin', "Post saved: {$post_id}");
    Debugger::printR('my-plugin', get_post($post_id));
});
```

#### Function Tracing

```php
function my_function($param) {
    Debugger::console('my-plugin', "Function called with: {$param}");
    
    $result = do_something($param);
    
    Debugger::console('my-plugin', "Function result: {$result}");
    return $result;
}
```

### Instance Management

#### `getInstances(): array`
Get all registered debugger instances.

#### `hasInstance(string $app_slug): bool`
Check if app has debugger instance.

#### `removeInstance(string $app_slug): bool`
Remove debugger instance.

### Production Safety

The debugger automatically disables in production:

```php
// Only runs if WP_DEBUG is true or explicitly enabled
Debugger::console('my-plugin', 'This only shows in development');

// Returns null in production
$result = Debugger::console('my-plugin', 'Debug message');
if ($result === null) {
    // Production mode - debug was skipped
}
```

### Integration Examples

#### With Model Classes

```php
class PostModel extends Model {
    protected function before_save($data) {
        Debugger::printR($this->config->slug, $data, 'Pre-save data');
        return parent::before_save($data);
    }
}
```

#### With AJAX Handlers

```php
Ajax::addAction('debug_action', function($data, $action, $ajax) {
    Debugger::console('my-plugin', "AJAX {$action} called");
    Debugger::printR('my-plugin', $data);
    
    $ajax->success(['debug' => 'complete']);
});
```

#### With Cache Operations

```php
$data = Cache::remember('expensive_data', function() {
    Debugger::timer('my-plugin', 'Cache miss - computing data');
    $result = expensive_computation();
    Debugger::timer('my-plugin', 'Cache miss - computing data', $start);
    return $result;
});
```

### Best Practices

1. **App Identification**: Always use consistent app_slug across your application
2. **Conditional Usage**: Check `isDev()` for expensive debug operations
3. **Meaningful Messages**: Use descriptive messages for easier debugging
4. **Context Data**: Include relevant context in breakpoints and errors
5. **Performance Monitoring**: Use timers for identifying bottlenecks
6. **Production Safety**: Never force development mode in production
7. **Clean Up**: Remove or comment out debug statements before release

## EnqueueManager

**Comprehensive asset management utility for WordPress scripts and styles with group organization, conditional loading, and dynamic localization.**

### Overview

The EnqueueManager class provides advanced asset management that:
- Organizes scripts and styles into logical groups
- Supports manual vs automatic enqueuing control
- Handles conditional loading based on context
- Manages localization data with global namespace support
- Provides inline script/style injection
- Integrates with WordPress enqueue system
- Supports modern script strategies (defer, async)

### Class Definition

```php
final class EnqueueManager
```

### Constructor & Factory

#### `__construct(Config|string $config_or_slug, ?string $base_url = null, ?string $base_path = null, ?string $version = null, bool $auto_enqueue_groups = false)`
Create new EnqueueManager instance.

**Parameters:**
- `$config_or_slug` (Config|string) - Config instance or app slug
- `$base_url` (string|null) - Base URL for assets
- `$base_path` (string|null) - Base path for assets
- `$version` (string|null) - Asset version for cache busting
- `$auto_enqueue_groups` (bool) - Whether to auto-enqueue groups when created

#### `create(Config|string $config_or_slug, ...): EnqueueManager`
Static factory method with same parameters.

### Group Management

#### `createScriptGroup(string $group_name, array $config = []): EnqueueManager`
Create a new script group.

**Group Config Options:**
- `condition` (callable|null) - Function to determine if group should load
- `hook` (string) - WordPress hook for enqueuing (default: 'wp_enqueue_scripts')
- `priority` (int) - Hook priority (default: 10)
- `admin_only` (bool) - Load only in admin (default: false)
- `frontend_only` (bool) - Load only on frontend (default: false)
- `description` (string) - Group description
- `auto_enqueue` (bool) - Override instance auto-enqueue setting

#### `createStyleGroup(string $group_name, array $config = []): EnqueueManager`
Create a new style group with same config options.

### Adding Assets to Groups

#### `addScriptToGroup(string $group_name, string $handle, string $src, array $deps = [], array $config = []): EnqueueManager`
Add script to existing group.

**Script Config Options:**
- `version` (string) - Script version
- `in_footer` (bool) - Load in footer (default: true)
- `condition` (callable|null) - Individual script condition
- `localize` (array) - Localization data ['object_name' => string, 'data' => array]
- `inline_before` (string) - Inline script before main script
- `inline_after` (string) - Inline script after main script
- `strategy` (string) - 'defer' or 'async' (WP 6.3+)

#### `addStyleToGroup(string $group_name, string $handle, string $src, array $deps = [], array $config = []): EnqueueManager`
Add style to existing group.

**Style Config Options:**
- `version` (string) - Style version
- `media` (string) - Media type (default: 'all')
- `condition` (callable|null) - Individual style condition
- `inline_before` (string) - Inline CSS before main stylesheet
- `inline_after` (string) - Inline CSS after main stylesheet

#### `addScriptsToGroup(string $group_name, array $scripts): EnqueueManager`
Add multiple scripts to group at once.

#### `addStylesToGroup(string $group_name, array $styles): EnqueueManager`
Add multiple styles to group at once.

### Individual Assets

#### `addScript(string $handle, string $src, array $deps = [], array $config = []): EnqueueManager`
Add individual script (not in a group).

**Additional Config Options:**
- `hook` (string) - WordPress hook for enqueuing
- `priority` (int) - Hook priority

#### `addStyle(string $handle, string $src, array $deps = [], array $config = []): EnqueueManager`
Add individual style with same additional config options.

### Localization Management

#### `addLocalization(string $handle, string $object_name, array $data): EnqueueManager`
Add localization data to script.

#### `updateLocalization(string $handle, array $data, bool $replace = false): EnqueueManager`
Update existing localization data dynamically.

#### `addGlobalData(array $data, bool $replace = false): EnqueueManager`
Add data to global wp_toolkit namespace.

### Inline Assets

#### `addInlineScript(string $handle, string $script, string $position = 'after'): EnqueueManager`
Add inline JavaScript to script handle.

**Positions:** `'before'`, `'after'`

#### `addInlineStyle(string $handle, string $style, string $position = 'after'): EnqueueManager`
Add inline CSS to style handle.

### Manual Enqueuing

#### `enqueueGroup(string $group_name, string $type = 'both'): EnqueueManager`
Manually enqueue a specific group.

**Types:** `'both'`, `'scripts'`, `'styles'`

#### `enqueueGroups(array $group_names, string $type = 'both'): EnqueueManager`
Enqueue multiple groups.

#### `enqueueByHandles(array $handles): EnqueueManager`
Enqueue specific assets by their handles.

### Utility Methods

#### `shouldLoadGroup(array $group_config): bool`
Check if group should be loaded based on conditions.

#### `getLocalizationData(string $handle): ?array`
Get current localization data for script.

#### `isGroupEnqueued(string $group_name, string $type = 'both'): bool`
Check if group has been enqueued.

#### `getScriptGroups(): array`
Get all registered script group names.

#### `getStyleGroups(): array`
Get all registered style group names.

### Implementation Guide

#### Step 1: Create EnqueueManager

```php
// Manual control (recommended)
$enqueue = EnqueueManager::create($config, null, null, null, false);

// Auto-enqueue mode
$enqueue = EnqueueManager::create($config, null, null, null, true);
```

#### Step 2: Create Groups and Add Assets

```php
// Create admin group
$enqueue->createScriptGroup('admin', [
    'admin_only' => true,
    'hook' => 'admin_enqueue_scripts'
])
->addScriptToGroup('admin', 'admin-js', 'js/admin.js', ['jquery'], [
    'localize' => [
        'object_name' => 'adminData',
        'data' => ['ajaxurl' => admin_url('admin-ajax.php')]
    ]
]);

// Create frontend group
$enqueue->createStyleGroup('frontend', [
    'frontend_only' => true,
    'condition' => fn() => !is_admin()
])
->addStyleToGroup('frontend', 'main-css', 'css/style.css');
```

#### Step 3: Manual Enqueuing (Recommended)

```php
// Conditional enqueuing
if (is_admin()) {
    $enqueue->enqueueGroup('admin');
} else {
    $enqueue->enqueueGroup('frontend');
}

// Specific page enqueuing
if (is_page('contact')) {
    $enqueue->enqueueByHandles(['contact-form-js', 'contact-styles']);
}
```

#### Step 4: Dynamic Localization

```php
// Update localization data at runtime
$enqueue->updateLocalization('admin-js', [
    'current_user' => wp_get_current_user()->ID,
    'nonce' => wp_create_nonce('admin_action')
]);

// Add to global namespace
$enqueue->addGlobalData([
    'api_url' => rest_url('myapp/v1/'),
    'version' => $config->get('version')
]);
```

### Conditional Loading Patterns

#### Context-Based Groups

```php
// Different groups for different contexts
$enqueue->createScriptGroup('dashboard', [
    'condition' => fn() => is_admin() && get_current_screen()->id === 'dashboard'
]);

$enqueue->createScriptGroup('shop', [
    'condition' => fn() => function_exists('is_woocommerce') && is_woocommerce()
]);
```

#### User Role Conditions

```php
$enqueue->createScriptGroup('editor-tools', [
    'condition' => fn() => current_user_can('edit_posts')
]);
```

#### Performance Conditions

```php
$enqueue->createScriptGroup('analytics', [
    'condition' => fn() => !is_user_logged_in() && !is_admin()
]);
```

### Advanced Features

#### Modern Script Strategies

```php
$enqueue->addScriptToGroup('performance', 'analytics', 'js/analytics.js', [], [
    'strategy' => 'defer',
    'in_footer' => true
]);
```

#### Bulk Asset Addition

```php
$scripts = [
    'main-js' => [
        'src' => 'js/main.js',
        'deps' => ['jquery'],
        'strategy' => 'defer'
    ],
    'utils-js' => [
        'src' => 'js/utils.js',
        'deps' => ['main-js']
    ]
];

$enqueue->addScriptsToGroup('frontend', $scripts);
```

#### Global Data Access

JavaScript can access global data:

```javascript
// Access app-specific data
console.log(window.wpToolkit.myApp.apiUrl);

// Access localization data
console.log(window.wpToolkit.myApp.adminData);
```

### Best Practices

1. **Manual Control**: Use `auto_enqueue_groups = false` for better control
2. **Group Organization**: Create logical groups (admin, frontend, specific features)
3. **Conditional Loading**: Use conditions to load assets only when needed
4. **Performance**: Use script strategies (defer/async) for non-critical scripts
5. **Localization**: Use global namespace for shared data, individual for script-specific
6. **Asset Paths**: Use relative paths for automatic URL resolution
7. **Versioning**: Let the manager handle versioning from config
8. **Error Handling**: Check if groups exist before enqueuing

## Filesystem

**WordPress filesystem operations utility with media library integration, upload management, and file type validation.**

### Overview

The Filesystem class provides comprehensive file management that:
- Handles file operations using WordPress filesystem API
- Integrates with WordPress media library
- Manages uploads with file type validation
- Creates app-specific upload directories with security
- Provides file information and metadata
- Supports directory scanning and management
- Uses dependency injection for configuration

### Class Definition

```php
class Filesystem
```

### Constructor & Factory

#### `__construct(Config|string $config_or_slug, array $allowed_types = [])`
Create new Filesystem instance.

**Parameters:**
- `$config_or_slug` (Config|string) - Config instance or app slug
- `$allowed_types` (array) - Additional allowed file extensions

#### `create(Config|string $config_or_slug, array $allowed_types = []): Filesystem`
Static factory method with same parameters.

### File Operations

#### `getContents(string $file_path): string|false`
Read file contents.

#### `putContents(string $file_path, string $contents, int $mode = 0644): bool`
Write contents to file with optional permissions.

#### `fileExists(string $file_path): bool`
Check if file exists.

#### `deleteFile(string $file_path): bool`
Delete a file.

#### `copyFile(string $source, string $destination, bool $overwrite = false): bool`
Copy file to new location.

#### `moveFile(string $source, string $destination, bool $overwrite = false): bool`
Move file to new location.

### Directory Operations

#### `createDirectory(string $dir_path, int $mode = 0755, bool $recursive = true): bool`
Create directory with permissions.

#### `deleteDirectory(string $dir_path, bool $recursive = false): bool`
Delete directory optionally recursive.

#### `scanDirectory(string $directory, bool $recursive = false, array $allowed_extensions = []): array`
Scan directory for files with optional extension filtering.

### File Information

#### `getFileSize(string $file_path): int|false`
Get file size in bytes.

#### `getModificationTime(string $file_path): int|false`
Get file modification timestamp.

#### `getFilePermissions(string $file_path): string|false`
Get file permissions.

#### `setFilePermissions(string $file_path, int $mode, bool $recursive = false): bool`
Set file permissions.

#### `getMimeType(string $file_path): string|false`
Get file MIME type.

#### `getFileInfo(string $file_path): array|false`
Get comprehensive file information.

**Returns:**
- `path` (string) - Full file path
- `filename` (string) - File name
- `extension` (string) - File extension
- `size` (int) - Size in bytes
- `size_formatted` (string) - Human-readable size
- `mime_type` (string) - MIME type
- `modified` (int) - Modification timestamp
- `modified_formatted` (string) - Formatted date
- `permissions` (string) - File permissions
- `is_allowed` (bool) - Whether file type is allowed

### Media Library Integration

#### `uploadToMediaLibrary(array $file_data, string $title = '', string $description = '', int $parent_post_id = 0): int|false`
Upload file to WordPress media library.

**Parameters:**
- `$file_data` (array) - File data in $_FILES format
- `$title` (string) - Optional file title
- `$description` (string) - Optional description
- `$parent_post_id` (int) - Optional parent post ID

**Returns:** Attachment ID or false on failure

#### `getMediaFileInfo(int $attachment_id): array|false`
Get media file information from attachment ID.

**Returns:**
- `id` (int) - Attachment ID
- `title` (string) - File title
- `description` (string) - File description
- `filename` (string) - File name
- `file_path` (string) - Server file path
- `url` (string) - Public URL
- `mime_type` (string) - MIME type
- `file_size` (int) - Size in bytes
- `upload_date` (string) - Upload date
- `metadata` (array) - WordPress attachment metadata

#### `deleteMediaFile(int $attachment_id, bool $force_delete = true): bool`
Delete file from media library.

### Upload Management

#### `getUploadDir(string $subdirectory = ''): array`
Get upload directory information.

**Returns:**
- `path` (string) - Server directory path
- `url` (string) - Public directory URL
- `subdir` (string) - Subdirectory path
- `basedir` (string) - Base upload directory
- `baseurl` (string) - Base upload URL

#### `createAppUploadDir(string $subdirectory = ''): array|false`
Create app-specific upload directory with security.

#### `getUniqueFilename(string $filename, string $subdirectory = ''): string`
Generate unique filename in upload directory.

### File Type Management

#### `isAllowedFileType(string $file_extension): bool`
Check if file extension is allowed.

#### `addAllowedFileTypes(array $types): Filesystem`
Add additional allowed file types.

#### `getAllowedFileTypes(): array`
Get all allowed file extensions.

#### `getAllowedMimeTypes(): array`
Get allowed MIME types mapping.

### Utility Methods

#### `formatFileSize(int $size, int $precision = 2): string`
Format file size for human-readable display.

### Implementation Guide

#### Step 1: Create Filesystem Instance

```php
// Basic initialization
$fs = Filesystem::create('my-plugin');

// With additional file types
$fs = Filesystem::create($config, ['xml', 'json', 'log']);
```

#### Step 2: File Operations

```php
// Read/write files
$content = $fs->getContents('/path/to/file.txt');
$fs->putContents('/path/to/output.txt', $content);

// File management
$fs->copyFile($source, $destination);
$fs->moveFile($old_path, $new_path, true);
```

#### Step 3: Directory Management

```php
// Create app upload directory
$upload_dir = $fs->createAppUploadDir('documents');

// Scan for files
$files = $fs->scanDirectory($upload_dir['path'], true, ['pdf', 'doc']);
```

#### Step 4: Media Library Integration

```php
// Upload to media library
$attachment_id = $fs->uploadToMediaLibrary($_FILES['upload'], 'Document Title');

// Get media info
$media_info = $fs->getMediaFileInfo($attachment_id);
```

### Security Features

#### App-Specific Directories

Creates isolated upload directories with security:

```php
$upload_dir = $fs->createAppUploadDir('private');
// Creates: /uploads/my-plugin/private/
// Includes: .htaccess file for protection
```

#### File Type Validation

```php
// Check before processing
if ($fs->isAllowedFileType('php')) {
    // This would be false by default
}

// Add safe types only
$fs->addAllowedFileTypes(['yaml', 'toml']);
```

### WordPress Integration

#### Upload Handling

```php
// Handle form uploads
if (isset($_FILES['document'])) {
    $file = $_FILES['document'];
    
    if ($fs->isAllowedFileType(pathinfo($file['name'], PATHINFO_EXTENSION))) {
        $attachment_id = $fs->uploadToMediaLibrary($file, 'User Document');
        
        if ($attachment_id) {
            // Success - file in media library
            $url = wp_get_attachment_url($attachment_id);
        }
    }
}
```

#### File Information Display

```php
$file_info = $fs->getFileInfo($file_path);
if ($file_info) {
    echo "Size: " . $file_info['size_formatted'];
    echo "Modified: " . $file_info['modified_formatted'];
    echo "Type: " . $file_info['mime_type'];
}
```

### Error Handling

```php
// Safe file operations
if ($fs->fileExists($file_path)) {
    $content = $fs->getContents($file_path);
    if ($content !== false) {
        // Process content
    }
}

// Directory creation with validation
$upload_dir = $fs->createAppUploadDir('temp');
if ($upload_dir) {
    // Directory created successfully
    $file_path = $upload_dir['path'] . '/data.json';
    $fs->putContents($file_path, json_encode($data));
}
```

### Default Allowed File Types

The class includes these file types by default:
- **Images**: jpg, jpeg, png, gif, webp, svg
- **Documents**: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, csv
- **Archives**: zip, tar, gz

### Best Practices

1. **File Type Validation**: Always validate file types before processing
2. **App Directories**: Use `createAppUploadDir()` for app-specific storage
3. **Error Handling**: Check return values for false/error states
4. **Security**: Never allow executable file types (php, js, etc.)
5. **Media Library**: Use media library for user-uploaded files
6. **Path Sanitization**: The class automatically sanitizes file paths
7. **Permissions**: Use appropriate file permissions for security
8. **Unique Names**: Use `getUniqueFilename()` to prevent conflicts


## InputValidator

**Comprehensive field validation utility with extensible validation rules and custom validator support.**

### Overview

The InputValidator class provides robust field validation that:
- Validates various input types with built-in rules
- Supports custom validation functions
- Handles WordPress-specific field types (wp_media, file uploads)
- Provides extensible error messaging
- Supports batch validation operations
- Includes global validators for cross-cutting concerns

### Class Definition

```php
class InputValidator
```

### Core Validation

#### `validate(string $type, mixed $value, array $field): bool|string`
Validate field value based on type and configuration.

**Parameters:**
- `$type` (string) - Input type
- `$value` (mixed) - Value to validate
- `$field` (array) - Field configuration

**Returns:** `true` if valid, error message string if invalid

### Supported Field Types

#### Text Fields
- `text`, `textarea`, `hidden`, `password` - Text validation with length/pattern
- `email` - Email format validation
- `url` - URL format validation
- `tel` - Phone number validation

#### Numeric Fields
- `number` - Numeric validation with min/max/step

#### Date/Time Fields
- `date`, `datetime-local`, `time` - Date format and range validation

#### Selection Fields
- `select`, `radio` - Option validation against defined choices
- `checkbox` - Boolean/checkbox validation

#### File Fields
- `file` - File upload validation (type, size)
- `wp_media` - WordPress media library validation

#### Color Fields
- `color` - Hex color code validation

### Field Configuration Options

#### Common Options
- `required` (bool) - Field is required
- `error_message` (string) - Custom error message
- `error_messages` (array) - Type-specific error messages
- `validate_callback` (callable) - Custom validation function

#### Attributes (type-specific)
- `minlength`/`maxlength` (int) - Text length limits
- `min`/`max` (int|float) - Numeric/date range limits
- `step` (float) - Numeric step validation
- `pattern` (string) - RegEx pattern for text
- `accept` (array) - Allowed file MIME types
- `max_size` (int) - Maximum file size in bytes

### Usage Examples

#### Basic Validation

```php
// Text validation
$result = InputValidator::validate('text', 'Hello', [
    'required' => true,
    'attributes' => ['minlength' => 3, 'maxlength' => 50]
]);

// Email validation
$result = InputValidator::validate('email', 'user@example.com', [
    'required' => true
]);
```

#### Custom Validators

```php
// Register custom validator
InputValidator::register_validator('phone', function($value, $field) {
    return preg_match('/^\+?[\d\s\-\(\)]+$/', $value) ? true : 'Invalid phone format';
});

// Use custom validator
$result = InputValidator::validate('phone', '+1234567890', []);
```

#### Batch Validation

```php
$results = InputValidator::validate_many([
    'name' => ['text', 'John Doe'],
    'email' => ['email', 'john@example.com'],
    'age' => ['number', 25]
], [
    'name' => ['required' => true],
    'email' => ['required' => true],
    'age' => ['attributes' => ['min' => 18]]
]);
```

### Advanced Features

#### Global Validators

```php
InputValidator::add_global_validator(function($value, $field, $type) {
    // Runs for all field types
    if (strlen($value) > 1000) {
        return 'Value too long';
    }
    return true;
});
```

#### Custom Error Messages

```php
InputValidator::set_error_message('required', 'This field cannot be empty');

// Field-specific messages
$field = [
    'required' => true,
    'error_messages' => [
        'required' => 'Name is required',
        'min_length' => 'Name must be at least {min} characters'
    ]
];
```

---

## Notification

**WordPress admin notification system with page targeting, expiration, and multi-app support.**

### Overview

The Notification class provides admin notice management that:
- Displays temporary messages in WordPress admin
- Supports different notification types and styling
- Handles page targeting (current, all, plugin-specific)
- Manages notification expiration and dismissal
- Supports both instance and static usage patterns
- Integrates with WordPress admin notice system

### Class Definition

```php
class Notification
```

### Constructor & Factory

#### `__construct(Config|string $config_or_slug, ?string $app_name = null)`
Create notification instance.

#### `create(Config|string $config_or_slug, ?string $app_name = null): Notification`
Static factory method.

### Instance Methods

#### `add(string $message, string $type = 'success', string|array $allowed_pages = 'current', ?int $expiration = null, bool $dismissible = true): bool`
Add notification message.

**Parameters:**
- `$message` (string) - Notification message (HTML allowed)
- `$type` (string) - Notification type: `success`, `error`, `warning`, `info`
- `$allowed_pages` (string|array) - Page targeting
- `$expiration` (int|null) - Expiration in seconds (default: 300)
- `$dismissible` (bool) - Can be dismissed by user

#### Type-Specific Methods

```php
$notification->success('Operation completed successfully');
$notification->error('Something went wrong');
$notification->warning('Please check your settings');
$notification->info('New feature available');
```

### Page Targeting Options

- `'current'` - Show on current page only
- `'all'` - Show on all admin pages
- `'plugin'` - Show on plugin pages only
- `['page1', 'page2']` - Show on specific pages

### Static Methods

#### `addStatic(Config|string $config_or_slug, string $message, string $type = 'success', string|array $allowed_pages = 'current', ?int $expiration = null, bool $dismissible = true): bool`
Add notification without creating instance.

#### Type-Specific Static Methods

```php
Notification::successStatic($config, 'Success message');
Notification::errorStatic($config, 'Error message');
Notification::warningStatic($config, 'Warning message');
Notification::infoStatic($config, 'Info message');
```

### Global Management

#### `initGlobal(): bool`
Initialize global notification display (call once in main plugin).

#### `displayAllNotifications(): void`
Display all notifications from all apps (automatically called).

### Implementation Guide

#### Step 1: Initialize Global System

```php
// In main plugin file (once per WordPress installation)
Notification::initGlobal();
```

#### Step 2: Create Instance

```php
// Per-plugin instance
$notification = Notification::create($config);
```

#### Step 3: Add Notifications

```php
// Instance method
$notification->success('Settings saved successfully', 'current', 300);

// Static method  
Notification::successStatic($config, 'Operation completed');
```

#### Step 4: Advanced Usage

```php
// Multi-page targeting
$notification->error('Database error', ['settings', 'dashboard'], 600);

// Plugin-specific notifications
$notification->info('Plugin updated', 'plugin');
```

### WordPress Integration

#### Hook Integration

```php
// Success after form submission
add_action('admin_post_save_settings', function() {
    if ($success) {
        Notification::successStatic($config, 'Settings saved');
    } else {
        Notification::errorStatic($config, 'Save failed');
    }
    wp_redirect($redirect_url);
});
```

#### Conditional Notifications

```php
// Show notifications based on user capability
if (current_user_can('manage_options')) {
    $notification->warning('Admin-only warning', 'all');
}

// Page-specific notifications
if (isset($_GET['page']) && $_GET['page'] === 'my-plugin-settings') {
    $notification->info('Welcome to settings', 'current');
}
```

### Notification Management

#### Manual Dismissal

```php
// Clear all notifications for app
$notification->clear();

// Dismiss specific notification
$notification->dismiss($notification_id);
```

#### Expiration Control

```php
// Short-lived notification (1 minute)
$notification->error('Temporary error', 'current', 60);

// Long-lived notification (1 hour)
$notification->info('Maintenance scheduled', 'all', 3600);
```

### Best Practices

1. **Global Init**: Call `initGlobal()` once in your main plugin file
2. **Page Targeting**: Use specific targeting to avoid notification spam
3. **Message Clarity**: Write clear, actionable messages
4. **Type Consistency**: Use appropriate types (error for failures, success for completions)
5. **Expiration**: Set reasonable expiration times
6. **Static vs Instance**: Use static methods for one-off notifications, instances for repeated use


## Page

**WordPress admin and frontend page management utility with Model integration, routing, and asset management.**

### Overview

The Page class provides comprehensive page management that:
- Creates WordPress admin menu and submenu pages
- Handles frontend page routing with custom URLs
- Integrates with Model classes for post type pages
- Manages page assets through EnqueueManager integration
- Provides template system with theme overrides
- Supports dynamic routing with regex patterns
- Handles capability checking and access control
- Offers URL generation for all page types

### Class Definition

```php
final class Page
```

### Constructor & Factory

#### `__construct(Config|string $config_or_slug, ?string $template_dir = null)`
Create Page instance.

#### `create(Config|string $config_or_slug, ?string $template_dir = null): Page`
Static factory method.

### Admin Page Management

#### `addMenuPage(string|Model $slug_or_model, array $config): Page`
Add main menu page or Model-based page.

**Admin Page Config Options:**
- `page_title` (string) - Page title for browser tab
- `menu_title` (string) - Menu item text
- `capability` (string) - Required user capability (default: 'manage_options')
- `callback` (callable|null) - Page render callback
- `icon` (string) - Menu icon (default: 'dashicons-admin-generic')
- `position` (int|null) - Menu position
- `template` (string) - Template file path
- `asset_groups` (array) - Asset groups to enqueue
- `asset_handles` (array) - Individual asset handles to enqueue

#### `addSubmenuPage(string|Model $slug_or_model, array $config): Page`
Add submenu page with additional config option:
- `parent_slug` (string) - Parent menu slug

#### `addAdminPages(array $pages): Page`
Add multiple admin pages at once.

### Frontend Page Management

#### `addFrontendPage(string $slug, array $config): Page`
Add frontend page with routing.

**Frontend Page Config Options:**
- `title` (string) - Page title
- `template` (string) - Template file path
- `callback` (callable|null) - Page render callback
- `public` (bool) - Whether page is public (default: true)
- `rewrite` (bool) - Enable URL rewriting (default: true)
- `query_vars` (array) - Custom query variables
- `capability` (string|null) - Required user capability
- `path` (string|null) - Custom URL path override
- `use_app_prefix` (bool) - Use app slug in URL (default: true)
- `regex` (string|null) - Custom regex pattern for dynamic routing
- `query_mapping` (array) - Map regex matches to query vars
- `asset_groups` (array) - Asset groups to enqueue
- `asset_handles` (array) - Individual asset handles to enqueue

#### `addFrontendPages(array $pages): Page`
Add multiple frontend pages at once.

### URL Generation

#### `getAdminUrl(string $slug, array $params = []): string`
Get admin page URL with optional parameters.

#### `getModelUrl(Model $model, array $params = []): string`
Get URL for Model-based admin page.

#### `getFrontendUrl(string $slug, array $params = []): string`
Get frontend page URL with optional parameters.

### Page Detection

#### `getCurrentPage(): array`
Get current page information.

#### `getCurrentPageSlug(): string`
Get current page slug.

#### `isPluginAdminPage(?string $slug = null): bool`
Check if current page is a plugin admin page.

#### `isPluginFrontendPage(?string $slug = null): bool`
Check if current page is a plugin frontend page.

### Asset Management Integration

#### `setAssetManager(EnqueueManager $asset_manager, array $default_groups = []): Page`
Set asset manager for automatic asset enqueuing.

#### `setDefaultAssetGroups(array $groups): Page`
Set default asset groups for page types.

**Default Groups Structure:**
```php
[
    'admin' => [
        'groups' => ['admin-scripts', 'admin-styles'],
        'handles' => ['admin-js', 'admin-css']
    ],
    'frontend' => [
        'groups' => ['frontend-scripts'],
        'handles' => ['frontend-js']
    ]
]
```

### Template Management

#### `setTemplateDirectory(string $template_dir): Page`
Set custom template directory.

#### `renderPage(string $slug, array $data = []): void`
Manually render a page with data.

### Utility Methods

#### `addDashboardWidget(string $widget_id, string $widget_title, callable $callback, string $capability = 'read'): Page`
Add WordPress dashboard widget.

#### `getAdminPageSlugs(): array`
Get all registered admin page slugs.

#### `getFrontendPageSlugs(): array`
Get all registered frontend page slugs.

### Implementation Guide

#### Step 1: Create Page Manager

```php
$page = Page::create($config, '/path/to/templates');

// With asset manager
$page->setAssetManager($enqueue_manager, [
    'admin' => ['groups' => ['admin-scripts']],
    'frontend' => ['groups' => ['frontend-scripts']]
]);
```

#### Step 2: Add Admin Pages

```php
// Main menu page
$page->addMenuPage('dashboard', [
    'page_title' => 'Plugin Dashboard',
    'menu_title' => 'My Plugin',
    'icon' => 'dashicons-chart-pie',
    'template' => 'admin/dashboard.php',
    'asset_groups' => ['admin-charts']
]);

// Submenu page
$page->addSubmenuPage('settings', [
    'parent_slug' => 'dashboard',
    'page_title' => 'Plugin Settings',
    'menu_title' => 'Settings',
    'template' => 'admin/settings.php'
]);
```

#### Step 3: Add Frontend Pages

```php
// Simple frontend page
$page->addFrontendPage('profile', [
    'title' => 'User Profile',
    'template' => 'frontend/profile.php',
    'capability' => 'read'
]);

// Dynamic routing with regex
$page->addFrontendPage('user', [
    'title' => 'User Details',
    'regex' => 'users/([^/]+)/?$',
    'query_mapping' => ['user_id' => '$matches[1]'],
    'template' => 'frontend/user-details.php'
]);
```

#### Step 4: Model Integration

```php
// Model-based admin page
$page->addMenuPage($userModel, [
    'menu_title' => 'Users',
    'icon' => 'dashicons-admin-users'
]);
```

### Dynamic Routing Examples

#### URL Pattern Mapping

```php
// URL: /my-plugin/product/123/reviews
$page->addFrontendPage('product-reviews', [
    'regex' => 'my-plugin/product/([0-9]+)/reviews/?$',
    'query_mapping' => ['product_id' => '$matches[1]'],
    'template' => 'product-reviews.php'
]);

// In template: get_query_var('product_id') returns '123'
```

#### Custom Paths

```php
// URL: /shop instead of /my-plugin/shop
$page->addFrontendPage('shop', [
    'path' => 'shop',
    'use_app_prefix' => false,
    'template' => 'shop.php'
]);
```

### Template System

#### Template Hierarchy

1. Plugin template directory: `/plugin/templates/admin/dashboard.php`
2. Theme override: `/theme/my-plugin-templates/dashboard.php`
3. Theme fallback: `/theme/plugin-templates/dashboard.php`

#### Template Data Access

```php
// In template file
$app_slug = $app_slug ?? '';
$page_config = $page_config ?? [];
$custom_data = $custom_data ?? [];

// Frontend pages also have:
$page_path = $page_path ?? '';
$user_id = get_query_var('user_id', ''); // From regex mapping
```

### WordPress Integration

#### Hook Integration

```php
// Automatic page registration
add_action('init', function() use ($page) {
    $page->addFrontendPage('api', [
        'callback' => function($data) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok']);
            exit;
        }
    ]);
});
```

#### Capability-Based Access

```php
$page->addAdminPage('advanced', [
    'capability' => 'administrator',
    'template' => 'admin/advanced.php'
]);

$page->addFrontendPage('member-area', [
    'capability' => 'subscriber',
    'template' => 'member-area.php'
]);
```

### Advanced Features

#### Dashboard Widgets

```php
$page->addDashboardWidget(
    'stats-widget',
    'Plugin Statistics',
    function() {
        echo '<p>Plugin usage statistics here</p>';
    },
    'manage_options'
);
```

#### Conditional Page Loading

```php
// In page callback
if ($page->isPluginAdminPage('dashboard')) {
    // Dashboard-specific logic
}

if ($page->getCurrentPageSlug() === 'settings') {
    // Settings page logic
}
```

### Best Practices

1. **Asset Management**: Always integrate with EnqueueManager for proper asset loading
2. **Template Organization**: Use clear directory structure (admin/, frontend/)
3. **Capability Checks**: Always set appropriate capabilities for security
4. **Model Integration**: Use Model classes for post type pages when possible
5. **URL Structure**: Keep frontend URLs SEO-friendly and logical
6. **Template Overrides**: Allow theme templates to override plugin templates
7. **Dynamic Routing**: Use regex patterns for flexible URL structures
8. **Error Handling**: Provide fallback templates and proper error messages

## Requirements

**System requirements validation utility for WordPress plugins with method chaining and comprehensive environment checking.**

### Overview

The Requirements class provides system requirement validation that:
- Checks PHP and WordPress version compatibility
- Validates active plugins and theme dependencies
- Supports multisite environment requirements
- Verifies Composer package installations
- Uses method chaining for readable requirement specifications
- Maintains cumulative validation state across all checks
- Provides simple boolean result for requirement status

### Class Definition

```php
class Requirements
```

### Constructor

#### `__construct()`
Create Requirements instance with default state (all requirements met).

### Core Methods

#### `met(): bool`
Check if all requirements have been satisfied.

**Returns:** `true` if all requirements met, `false` otherwise

### Version Requirements

#### `php(string $minVersion): Requirements`
Check minimum PHP version requirement.

**Parameters:**
- `$minVersion` (string) - Minimum PHP version (e.g., '8.1', '8.0.0')

#### `wp(string $minVersion): Requirements`
Check minimum WordPress version requirement.

**Parameters:**
- `$minVersion` (string) - Minimum WordPress version (e.g., '6.0', '5.9.0')

### Environment Requirements

#### `multisite(bool $required): Requirements`
Check multisite environment requirement.

**Parameters:**
- `$required` (bool) - Whether multisite is required (`true`) or forbidden (`false`)

#### `theme(string $parentTheme): Requirements`
Check active parent theme requirement.

**Parameters:**
- `$parentTheme` (string) - Required parent theme slug

### Dependency Requirements

#### `plugins(array $plugins): Requirements`
Check required plugin dependencies.

**Parameters:**
- `$plugins` (array) - List of required plugin paths
    - Format: `['plugin-folder/plugin-file.php', 'another-plugin/main.php']`

#### `packages(array $packages): Requirements`
Check Composer package dependencies.

**Parameters:**
- `$packages` (array) - List of required Composer packages
    - Format: `['vendor/package-name', 'another/package']`

### Usage Examples

#### Basic Version Checking

```php
$requirements = new Requirements();
$requirements->php('8.1')->wp('6.0');

if (!$requirements->met()) {
    // Handle requirement failure
    return;
}
```

#### Comprehensive Requirements

```php
$requirements = (new Requirements())
    ->php('8.0')
    ->wp('5.9')
    ->multisite(false)
    ->plugins(['woocommerce/woocommerce.php'])
    ->packages(['monolog/monolog']);

if ($requirements->met()) {
    // Initialize plugin
}
```

#### Plugin Dependency Checking

```php
// Check for WooCommerce and Advanced Custom Fields
$requirements->plugins([
    'woocommerce/woocommerce.php',
    'advanced-custom-fields/acf.php'
]);
```

#### Theme Compatibility

```php
// Require specific parent theme
$requirements->theme('twentytwentythree');
```

### Implementation Patterns

#### Plugin Initialization Guard

```php
class MyPlugin {
    public static function init(): void {
        $requirements = new Requirements();
        
        if (!$requirements->php('8.1')->wp('6.0')->met()) {
            add_action('admin_notices', [self::class, 'requirementNotice']);
            return;
        }
        
        // Initialize plugin
        self::loadPlugin();
    }
    
    public static function requirementNotice(): void {
        echo '<div class="notice notice-error"><p>';
        echo 'Plugin requires PHP 8.1+ and WordPress 6.0+';
        echo '</p></div>';
    }
}
```

#### Conditional Feature Loading

```php
// Load features based on environment
$requirements = new Requirements();

if ($requirements->plugins(['woocommerce/woocommerce.php'])->met()) {
    // Load WooCommerce integration
    new WooCommerceIntegration();
}

if ($requirements->multisite(true)->met()) {
    // Load multisite-specific features
    new MultisiteFeatures();
}
```

#### Development vs Production

```php
$requirements = new Requirements();

// Base requirements
$requirements->php('8.0')->wp('5.9');

// Additional development requirements
if (defined('WP_DEBUG') && WP_DEBUG) {
    $requirements->packages(['phpunit/phpunit', 'squizlabs/php_codesniffer']);
}

if (!$requirements->met()) {
    // Handle missing requirements
}
```

### Method Chaining Benefits

The fluent interface allows readable requirement specifications:

```php
// Clear requirement specification
$ready = (new Requirements())
    ->php('8.1')                    // Minimum PHP version
    ->wp('6.0')                     // Minimum WordPress version
    ->multisite(false)              // Not multisite
    ->theme('storefront')           // Requires Storefront theme
    ->plugins([                     // Required plugins
        'woocommerce/woocommerce.php',
        'jetpack/jetpack.php'
    ])
    ->packages([                    // Required packages
        'guzzlehttp/guzzle',
        'monolog/monolog'
    ])
    ->met();                        // Check result
```

### WordPress Integration

#### Plugin Header Validation

```php
// In main plugin file
$requirements = new Requirements();
if (!$requirements->php('8.1')->wp('6.0')->met()) {
    add_action('admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            __('This plugin requires PHP 8.1+ and WordPress 6.0+')
        );
    });
    return; // Stop plugin execution
}
```

#### Activation Hook

```php
register_activation_hook(__FILE__, function() {
    $requirements = new Requirements();
    
    if (!$requirements->php('8.1')->wp('6.0')->met()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Plugin requires PHP 8.1+ and WordPress 6.0+');
    }
});
```

### Error Handling Patterns

#### Graceful Degradation

```php
$requirements = new Requirements();

// Core functionality requirements
if (!$requirements->php('8.0')->wp('5.9')->met()) {
    return; // Cannot load at all
}

// Optional feature requirements
if ($requirements->plugins(['woocommerce/woocommerce.php'])->met()) {
    // Load e-commerce features
} else {
    // Skip e-commerce integration
}
```

#### User-Friendly Messages

```php
$requirements = new Requirements();

if (!$requirements->php('8.1')->met()) {
    $message = sprintf(
        'Plugin requires PHP 8.1 or higher. Current version: %s',
        PHP_VERSION
    );
    // Display user-friendly error
}

if (!$requirements->wp('6.0')->met()) {
    $message = sprintf(
        'Plugin requires WordPress 6.0 or higher. Current version: %s',
        get_bloginfo('version')
    );
    // Display user-friendly error
}
```

### Best Practices

1. **Early Checking**: Validate requirements before any plugin initialization
2. **Clear Messages**: Provide specific version requirements in error messages
3. **Graceful Degradation**: Disable features rather than entire plugin when possible
4. **Plugin Dependencies**: Check for specific plugin files, not just plugin names
5. **Version Formats**: Use semantic versioning for consistency
6. **Multisite Awareness**: Consider both single-site and multisite compatibility
7. **Development Requirements**: Separate development-only package requirements

## RestRoute

**Multi-version WordPress REST API management utility with deprecation handling, middleware support, and comprehensive route management.**

### Overview

The RestRoute class provides advanced REST API management that:
- Supports multiple API versions with seamless migration paths
- Handles route registration with automatic WordPress integration
- Manages API deprecation with warning headers and removal dates
- Provides middleware system for request processing
- Offers standardized response formatting
- Includes permission checking and nonce validation
- Supports route copying for backward compatibility
- Generates comprehensive API documentation

### Class Definition

```php
class RestRoute
```

### Constructor & Factory

#### `__construct(Config|string $config_or_slug, array $supported_versions = ['v1'], string $default_version = 'v1', ?string $base_namespace = null)`
Create RestRoute instance with multi-version support.

**Parameters:**
- `$config_or_slug` (Config|string) - Config instance or app slug
- `$supported_versions` (array) - List of supported API versions
- `$default_version` (string) - Default API version
- `$base_namespace` (string|null) - Custom base namespace

#### `create(Config|string $config_or_slug, ...): RestRoute`
Static factory method with same parameters.

### Version Management

#### `registerVersion(string $version, array $config = []): RestRoute`
Register new API version.

**Version Config Options:**
- `routes` (array) - Registered routes for this version
- `middleware` (array) - Version-specific middleware
- `deprecated` (bool) - Whether version is deprecated
- `deprecation_date` (string|null) - Deprecation date (ISO 8601)
- `removal_date` (string|null) - Planned removal date
- `successor_version` (string|null) - Recommended successor version
- `description` (string) - Version description
- `changelog` (array) - Version changelog

#### `deprecateVersion(string $version, string $deprecation_date, ?string $removal_date = null, ?string $successor_version = null): RestRoute`
Mark version as deprecated with migration timeline.

#### `copyRoutes(string $from_version, string $to_version, array $exclude_routes = []): RestRoute`
Copy routes between versions for backward compatibility.

### Route Registration

#### `addRoute(string $version, string $route, array $config): RestRoute`
Add route to specific version.

**Route Config Options:**
- `methods` (string) - HTTP methods ('GET', 'POST', 'PUT', 'DELETE')
- `callback` (callable) - Route handler function
- `permission_callback` (callable) - Permission check function
- `args` (array) - Route parameters with validation
- `validate_callback` (callable|null) - Custom validation function
- `sanitize_callback` (callable|null) - Custom sanitization function
- `deprecated` (bool) - Whether route is deprecated
- `deprecation_message` (string) - Deprecation message
- `rate_limit` (array|null) - Rate limiting configuration
- `cache_ttl` (int|null) - Cache time-to-live

#### HTTP Method Shortcuts

```php
$api->get('v1', '/posts', [$controller, 'getPosts']);
$api->post('v1', '/posts', [$controller, 'createPost']);
$api->put('v1', '/posts/(?P<id>\d+)', [$controller, 'updatePost']);
$api->delete('v1', '/posts/(?P<id>\d+)', [$controller, 'deletePost']);
```

#### `addRoutes(string $version, array $routes): RestRoute`
Add multiple routes to version at once.

### Route Parameters

Route parameters support comprehensive validation:

**Parameter Config Options:**
- `description` (string) - Parameter description
- `type` (string) - Parameter type ('string', 'integer', 'boolean', 'array')
- `required` (bool) - Whether parameter is required
- `default` (mixed) - Default value
- `enum` (array) - Allowed values
- `validate_callback` (callable) - Custom validation function
- `sanitize_callback` (callable) - Custom sanitization function

### Middleware System

#### `addMiddleware(string $version, callable $middleware, int $priority = 10): RestRoute`
Add version-specific middleware.

#### `addGlobalMiddleware(callable $middleware, int $priority = 10): RestRoute`
Add global middleware for all versions.

### Response Helpers

#### `successResponse(mixed $data = null, string $message = '', int $status = 200): WP_REST_Response`
Create standardized success response.

#### `errorResponse(string $code, string $message, mixed $data = null, int $status = 400): WP_Error`
Create standardized error response.

#### `checkPermissions(string $capability = 'read'): bool|WP_Error`
Validate user permissions.

#### `verifyNonce(WP_REST_Request $request, string $action, string $param = '_wpnonce'): bool|WP_Error`
Validate request nonce.

### URL & Documentation

#### `getRouteUrl(string $version, string $route, array $params = []): string`
Generate URL for specific route and version.

#### `getAvailableVersions(bool $include_deprecated = true): array`
Get list of available API versions.

#### `getApiDocumentation(): array`
Generate comprehensive API documentation.

### Implementation Guide

#### Step 1: Create Multi-Version API

```php
$api = RestRoute::create($config, ['v1', 'v2'], 'v2');

// Register v1 routes
$api->get('v1', '/posts', [PostController::class, 'getPosts']);
$api->post('v1', '/posts', [PostController::class, 'createPost']);

// Copy to v2 and add new features
$api->copyRoutes('v1', 'v2');
$api->get('v2', '/posts/search', [PostController::class, 'searchPosts']);
```

#### Step 2: Add Route with Validation

```php
$api->post('v1', '/posts', [PostController::class, 'createPost'], [
    'permission_callback' => function() {
        return current_user_can('publish_posts');
    },
    'args' => [
        'title' => [
            'type' => 'string',
            'required' => true,
            'validate_callback' => function($value) {
                return strlen($value) >= 5;
            }
        ],
        'content' => [
            'type' => 'string',
            'sanitize_callback' => 'wp_kses_post'
        ],
        'status' => [
            'type' => 'string',
            'enum' => ['publish', 'draft'],
            'default' => 'draft'
        ]
    ]
]);
```

#### Step 3: Add Middleware

```php
// Global authentication middleware
$api->addGlobalMiddleware(function($request, $route_meta) {
    if (!is_user_logged_in()) {
        return new WP_Error('unauthorized', 'Login required', ['status' => 401]);
    }
    return null; // Continue processing
});

// Version-specific rate limiting
$api->addMiddleware('v1', function($request, $route_meta) {
    // Rate limiting logic
    return null;
});
```

#### Step 4: Handle Deprecation

```php
// Deprecate v1, recommend v2
$api->deprecateVersion('v1', '2024-01-01', '2024-06-01', 'v2');

// Routes will automatically include deprecation headers
```

### Controller Pattern

```php
class PostController {
    public static function getPosts(WP_REST_Request $request): WP_REST_Response {
        $posts = get_posts([
            'numberposts' => $request->get_param('per_page') ?? 10,
            'post_status' => 'publish'
        ]);
        
        return new WP_REST_Response($posts, 200);
    }
    
    public static function createPost(WP_REST_Request $request): WP_REST_Response {
        $post_id = wp_insert_post([
            'post_title' => $request->get_param('title'),
            'post_content' => $request->get_param('content'),
            'post_status' => $request->get_param('status')
        ]);
        
        if (is_wp_error($post_id)) {
            return new WP_Error('create_failed', $post_id->get_error_message(), ['status' => 400]);
        }
        
        return new WP_REST_Response(['id' => $post_id], 201);
    }
}
```

### Advanced Features

#### Dynamic Route Parameters

```php
// Route with dynamic parameter
$api->get('v1', '/users/(?P<user_id>\d+)/posts', function($request) {
    $user_id = $request->get_param('user_id');
    return get_posts(['author' => $user_id]);
}, [
    'args' => [
        'user_id' => [
            'type' => 'integer',
            'validate_callback' => function($value) {
                return $value > 0 && get_user_by('id', $value);
            }
        ]
    ]
]);
```

#### Comprehensive Error Handling

```php
class APIController {
    protected RestRoute $api;
    
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error {
        try {
            // Permission check
            $permission_check = $this->api->checkPermissions('edit_posts');
            if (is_wp_error($permission_check)) {
                return $permission_check;
            }
            
            // Nonce verification
            $nonce_check = $this->api->verifyNonce($request, 'api_action');
            if (is_wp_error($nonce_check)) {
                return $nonce_check;
            }
            
            // Process request
            $result = $this->processData($request->get_params());
            
            return $this->api->successResponse($result, 'Operation completed');
            
        } catch (Exception $e) {
            return $this->api->errorResponse('processing_error', $e->getMessage());
        }
    }
}
```

#### API Documentation Generation

```php
// Generate API docs
$docs = $api->getApiDocumentation();

// Create documentation endpoint
$api->get('v1', '/docs', function() use ($docs) {
    return new WP_REST_Response($docs);
});
```

### WordPress Integration

#### Automatic Discovery

The API automatically adds discovery links to HTML head:

```html
<link rel="https://api.w.org/" href="/wp-json/my-app/v1" data-app="my-app" />
```

#### Response Headers

All responses include version information:

```
X-API-Version: v1
X-API-App: my-app
Warning: 299 - "API version deprecated" (for deprecated versions)
X-API-Deprecation-Date: 2024-01-01
X-API-Removal-Date: 2024-06-01
X-API-Successor-Version: v2
```

### Best Practices

1. **Version Strategy**: Plan API versions with clear migration paths
2. **Deprecation Timeline**: Provide adequate notice before removing versions
3. **Middleware Ordering**: Use priority system for middleware execution order
4. **Parameter Validation**: Always validate and sanitize input parameters
5. **Error Consistency**: Use standardized error response format
6. **Documentation**: Keep API documentation current and comprehensive
7. **Security**: Implement proper authentication and authorization
8. **Rate Limiting**: Consider implementing rate limiting for public APIs

## Settings

**WordPress settings management utility with validation, sanitization, and automatic Settings API integration.**

### Overview

The Settings class provides comprehensive settings management that:
- Integrates with WordPress Settings API automatically
- Supports field validation and sanitization with custom callbacks
- Renders form fields with proper HTML attributes
- Handles import/export of settings data
- Manages cache clearing and notifications
- Provides POST data processing with nonce verification
- Supports grouped settings organization

### Class Definition

```php
class Settings
```

### Constructor & Factory

#### `__construct(array $settings = [], Config|string|null $config_or_slug = null, ?string $option_prefix = null, ?callable $notification_callback = null)`
Create Settings instance.

#### `create(array $settings = [], ...): Settings`
Static factory method with same parameters.

### Setting Configuration

#### `addSetting(string $key, array $config): bool`
Add single setting configuration.

**Setting Config Options:**
- `type` (string) - Field type: `text`, `textarea`, `email`, `url`, `password`, `number`, `select`, `radio`, `checkbox`
- `label` (string) - Field label text
- `description` (string) - Field description/help text
- `default` (mixed) - Default value
- `group` (string) - Settings group name (default: 'general')
- `sanitize_callback` (callable|null) - Custom sanitization function
- `validate_callback` (callable|null) - Custom validation function
- `choices` (array) - Options for select/radio fields (key => label pairs)
- `attributes` (array) - HTML attributes for field rendering
    - `placeholder` (string) - Input placeholder text
    - `class` (string) - CSS class names
    - `min`/`max` (int) - Number field limits
    - `rows`/`cols` (int) - Textarea dimensions
    - `readonly` (bool) - Make field read-only
    - `disabled` (bool) - Disable field

#### `addSettings(array $settings): Settings`
Add multiple settings at once.

### Value Management

#### `get(string $key, mixed $default = null): mixed`
Get setting value with optional default.

#### `set(string $key, mixed $value): bool`
Set setting value with validation and sanitization.

#### `delete(string $key): bool`
Delete setting.

#### `getAll(?string $group = null): array`
Get all settings, optionally filtered by group.

#### `reset(string $key): bool`
Reset setting to default value.

#### `resetAll(?string $group = null): bool`
Reset all settings to defaults.

### Data Operations

#### `import(array $data, bool $validate = true): array`
Import settings from array with validation.

#### `export(?string $group = null): array`
Export settings as array.

#### `saveFromArray(array $data, bool $validate = true): array`
Save multiple settings from array.

#### `saveFromPost(array $post_data, ?string $nonce_field = null, ?string $nonce_action = null, ?array $fields = null): array`
Save settings from $_POST data with nonce verification.

### Field Rendering

#### `renderField(string $key, array $args = []): string`
Render HTML form field for setting.

### Notifications

#### `sendSuccessNotification(string $message, array $args = []): bool`
Send success notification.

#### `sendErrorNotification(string $message, array $args = []): bool`
Send error notification.

#### `sendWarningNotification(string $message, array $args = []): bool`
Send warning notification.

#### `sendInfoNotification(string $message, array $args = []): bool`
Send info notification.

### Implementation Guide

#### Step 1: Create Settings Manager

```php
$settings = Settings::create([
    'api_key' => [
        'type' => 'password',
        'label' => 'API Key',
        'description' => 'Your service API key',
        'group' => 'api'
    ],
    'cache_duration' => [
        'type' => 'number',
        'label' => 'Cache Duration',
        'default' => 3600,
        'attributes' => ['min' => 60, 'max' => 86400]
    ]
], $config);
```

#### Step 2: Handle Form Submission

```php
if ($_POST) {
    $results = $settings->saveFromPost($_POST, '_wpnonce', 'save_settings');
    
    if (array_search(false, $results) === false) {
        $settings->sendSuccessNotification('Settings saved successfully');
    }
}
```

#### Step 3: Render Settings Form

```php
echo '<form method="post">';
wp_nonce_field('save_settings');
echo $settings->renderField('api_key');
echo $settings->renderField('cache_duration');
echo '<input type="submit" value="Save Settings">';
echo '</form>';
```

### Advanced Usage

#### Custom Validation

```php
$settings->addSetting('email', [
    'type' => 'email',
    'validate_callback' => function($value) {
        return is_email($value) ? true : false;
    }
]);
```

#### Grouped Settings

```php
$results = $settings->getAll('api'); // Get only API group
$settings->resetAll('cache'); // Reset only cache group
```

---

## ViewLoader

**Template loading utility with caching, inheritance, and multi-path support.**

### Overview

The ViewLoader class provides advanced template management that:
- Loads templates from multiple prioritized paths
- Supports template caching with automatic invalidation
- Provides layout inheritance with sections system
- Manages global data available to all templates
- Handles multiple file extensions and index files
- Offers nested view loading and data injection
- Includes comprehensive error handling

### Class Definition

```php
class ViewLoader
```

### Core Loading Methods

#### `load(string $view, array $data = [], bool $echo = true, ?string $base_path = null): string|false`
Load template with data injection.

#### `get(string $view, array $data = [], ?string $base_path = null): string`
Load template and return output without echoing.

#### `exists(string $view, ?string $base_path = null): bool`
Check if template exists.

### Path Management

#### `add_path(string $path, int $priority = 10): void`
Add template search path with priority.

#### `remove_path(string $path): void`
Remove template path.

#### `get_paths(): array`
Get all registered paths.

#### `clear_paths(): void`
Clear all template paths.

### Global Data

#### `set_global_data(array $data): void`
Set data available to all templates.

#### `get_global_data(?string $key = null): mixed`
Get global data.

#### `clear_global_data(?string $key = null): void`
Clear global data.

### Template Caching

#### `enable_cache(int $duration = 3600, string $group = 'wptoolkit_views'): void`
Enable template caching.

#### `disable_cache(): void`
Disable template caching.

#### `clear_cache(): int`
Clear template cache.

### Layout System

#### `start_section(string $name): void`
Start capturing content for a section.

#### `end_section(): void`
End current section capture.

#### `get_section(string $name, string $default = ''): string`
Get section content.

#### `layout(string $layout, string $content_view, array $data = [], bool $echo = true): string|false`
Load layout with content sections.

### File Extensions

#### `add_extensions(array $extensions): void`
Add supported file extensions.

#### `get_extensions(): array`
Get supported extensions.

### Implementation Guide

#### Step 1: Setup Template Paths

```php
// Add template directories with priorities
ViewLoader::add_path('/theme/plugin-templates', 5); // Highest priority
ViewLoader::add_path('/plugin/templates', 10);      // Default priority
ViewLoader::add_path('/plugin/fallback', 20);       // Lowest priority
```

#### Step 2: Load Simple Templates

```php
// Echo template output
ViewLoader::load('admin/dashboard', ['title' => 'Dashboard']);

// Get template output
$content = ViewLoader::get('emails/welcome', ['user' => $user_data]);
```

#### Step 3: Use Layout Inheritance

```php
// In content template (admin/settings.php):
<?php $view->section('title'); ?>Settings<?php $view->end_section(); ?>
<?php $view->section('content'); ?>
<form>...</form>
<?php $view->end_section(); ?>

// Load with layout:
ViewLoader::layout('layouts/admin', 'admin/settings', ['page' => 'settings']);
```

#### Step 4: Enable Caching

```php
// Enable caching for production
ViewLoader::enable_cache(3600, 'plugin_views');

// Set global data
ViewLoader::set_global_data([
    'app_name' => 'My Plugin',
    'version' => '1.0.0'
]);
```

### Template Features

#### Helper Object

Templates receive a `$view` helper object:

```php
// In template file:
$view->include('partials/header', ['title' => $page_title]);
$view->section('sidebar'); ?>
<div>Sidebar content</div>
<?php $view->end_section();
echo $view->load('partials/form', $form_data);
```

#### Layout Template Example

```php
<!-- layouts/admin.php -->
<div class="wrap">
    <h1><?php $view->yield('title', 'Default Title'); ?></h1>
    <div class="content">
        <?php $view->yield('content'); ?>
    </div>
    <aside class="sidebar">
        <?php $view->yield('sidebar', '<p>No sidebar content</p>'); ?>
    </aside>
</div>
```

### Path Resolution

Template paths are searched in priority order:
1. Override base path (if provided)
2. Registered paths (by priority)
3. Default fallback paths

File resolution order:
1. `view.php` (exact match)
2. `view.html` (other extensions)
3. `view/index.php` (directory with index)

### Error Handling

```php
// Check if template exists
if (ViewLoader::exists('admin/advanced')) {
    ViewLoader::load('admin/advanced', $data);
} else {
    ViewLoader::load('admin/fallback', $data);
}

// Handle loading errors
$output = ViewLoader::get('template', $data);
if ($output === false) {
    error_log('Failed to load template');
}
```

### Best Practices

1. **Path Priority**: Use priority system for theme overrides
2. **Caching**: Enable caching in production environments
3. **Global Data**: Set app-wide data once globally
4. **Error Handling**: Check template existence for critical templates
5. **Layout System**: Use sections for consistent page structure
6. **File Organization**: Organize templates in logical subdirectories
7. **Extension Support**: Add custom extensions as needed

# MetaBox

**Type-safe custom fields framework with validation, callbacks, and WordPress integration.**

### Overview

The MetaBox class provides a comprehensive custom fields system that:
- Creates WordPress meta boxes with advanced field types
- Supports field validation and sanitization with custom callbacks
- Provides lifecycle hooks (pre/post save, validate, success, error)
- Handles quick edit integration automatically
- Offers AJAX data fetching capabilities
- Integrates with caching system for performance
- Supports template-based field rendering

### Class Definition

```php
final class MetaBox
```

### Constructor & Factory

#### `__construct(string $id, string $title, string $screen, ?Config $config = null)`
Create MetaBox instance.

#### `create(string $id, string $title, string $screen, ?Config $config = null): MetaBox`
Static factory method.

**Parameters:**
- `$id` (string) - Unique meta box identifier
- `$title` (string) - Meta box title
- `$screen` (string) - Post type where meta box appears
- `$config` (Config|null) - Optional configuration instance

### Configuration Methods

#### `set(string $property, mixed $value): MetaBox`
Generic setter with fluent interface.

#### `set_nonce(string $nonce): MetaBox`
Set custom nonce key.

#### `set_prefix(string $prefix): MetaBox`
Set meta key prefix.

#### `set_caching(bool $enable, int $duration = 3600): MetaBox`
Enable/disable field value caching.

### Setup & Integration

#### `setup_actions(): MetaBox`
Register WordPress hooks and actions.

### Field Management

#### `add_field(string $id, string $label, string $type, array $options = [], array $attributes = [], array $config = []): MetaBox`
Add single field to meta box.

**Field Types:** `text`, `textarea`, `email`, `url`, `password`, `number`, `select`, `radio`, `checkbox`, `date`, `color`, `file`, `wp_media`

**Config Options:**
- `default` (mixed) - Default field value
- `allow_quick_edit` (bool) - Enable quick edit support
- `description` (string) - Field description/help text
- `required` (bool) - Field is required
- `sanitize_callback` (callable) - Custom sanitization function
- `validate_callback` (callable) - Custom validation function

**Attributes:** Standard HTML attributes (`placeholder`, `class`, `min`, `max`, `rows`, `cols`, `readonly`, `disabled`, `multiple`)

#### `add_fields(array $fields): MetaBox`
Add multiple fields at once.

**Field Array Structure:**
```php
[
    'id' => 'field_id',
    'label' => 'Field Label',
    'type' => 'text',
    'options' => [], // For select/radio
    'attributes' => [],
    'config' => []
]
```

### Value Management

#### `get_field_value(string $field_id, ?int $post_id = null, bool $single = true): mixed`
Get field value for post.

#### `save_field(int $post_id, string $field_id, mixed $value): bool`
Save single field value.

#### `all_meta(int $post_id, string|bool $strip = null): array`
Get all meta values for post.

### Callback System

#### `on(string $event, callable $callback): MetaBox`
Add lifecycle callback.

**Events:** `error`, `success`, `pre_save`, `post_save`, `pre_validate`, `post_validate`

#### Shorthand Methods
```php
$metabox->onError(callable $callback): MetaBox
$metabox->onSuccess(callable $callback): MetaBox
$metabox->onPreSave(callable $callback): MetaBox
$metabox->onPostSave(callable $callback): MetaBox
```

### Utility Methods

#### `save(int $post_id): bool|WP_Error`
Save all meta box fields with validation.

#### `get_fields(): array`
Get all registered field configurations.

#### `get_field(string $by, mixed $value): ?array`
Get specific field configuration.

#### `get_last_errors(): array`
Get validation errors from last save attempt.

### Customization

#### `set_callback(\Closure $callback): MetaBox`
Set custom rendering callback.

#### `register_sanitizer(string $type, callable $sanitizer): MetaBox`
Register custom field type sanitizer.

#### `set_input_type_html(string $type, \Closure $callback): MetaBox`
Set custom HTML generator for field type.

### Implementation Guide

#### Step 1: Create MetaBox

```php
$metabox = MetaBox::create('product_info', 'Product Information', 'product', $config)
    ->set_prefix('product_')
    ->set_caching(true, 1800)
    ->setup_actions();
```

#### Step 2: Add Fields

```php
$metabox->add_field('price', 'Price', 'number', [], [
    'min' => 0,
    'step' => 0.01,
    'placeholder' => '0.00'
], [
    'required' => true,
    'description' => 'Product price in USD'
]);

$metabox->add_field('images', 'Gallery', 'wp_media', [], [
    'multiple' => true
], [
    'allow_quick_edit' => false
]);
```

#### Step 3: Add Callbacks

```php
$metabox->onPreSave(function($post_id, $metabox) {
    // Pre-save validation
})
->onSuccess(function($post_id, $metabox) {
    // Clear related caches
});
```

#### Step 4: Register Save Hook

```php
add_action('save_post_product', [$metabox, 'save']);
```

### Advanced Features

#### Custom Field Types

```php
$metabox->register_sanitizer('currency', function($value) {
    return number_format((float)$value, 2, '.', '');
});

$metabox->set_input_type_html('currency', function($id, $data) {
    return "<input type='number' step='0.01' name='{$id}' value='{$data['default']}' />";
});
```

#### Bulk Field Addition

```php
$fields = [
    ['id' => 'title', 'label' => 'Title', 'type' => 'text'],
    ['id' => 'description', 'label' => 'Description', 'type' => 'textarea']
];
$metabox->add_fields($fields);
```

### WordPress Integration

MetaBox automatically handles:
- WordPress meta box registration
- Quick edit field integration
- AJAX data fetching
- Nonce verification
- User capability checking
- Auto-save prevention

### Best Practices

1. **Prefix Fields**: Use consistent meta key prefixes to avoid conflicts
2. **Validation**: Always validate required and complex fields
3. **Caching**: Enable caching for frequently accessed meta values
4. **Quick Edit**: Only enable quick edit for simple field types
5. **Callbacks**: Use lifecycle callbacks for related operations
6. **Error Handling**: Check `get_last_errors()` after save operations

# Model

**Enterprise-grade abstract base class for WordPress custom post types with singleton pattern, MetaBox integration, and comprehensive admin features.**

### Overview

The Model class provides a robust foundation for custom post types that:
- Enforces singleton pattern for all child classes
- Integrates seamlessly with MetaBox system
- Supports custom admin columns with quick edit
- Provides advanced search with relevance scoring
- Handles authentication and access control
- Offers comprehensive CRUD operations with caching
- Manages custom taxonomies automatically
- Includes AJAX endpoints for search/autocomplete
- Supports lifecycle management (run, pause, deactivate)

### Class Definition

```php
abstract class Model
```

### Required Constants

Child classes must define these constants:

```php
protected const POST_TYPE = 'your_post_type';
protected const META_PREFIX = 'your_prefix_';
protected const REQUIRES_AUTHENTICATION = false; // true|false
protected const VIEW_CAPABILITY = 'read'; // WordPress capability
```

### Singleton Pattern

#### `get_instance(?Config $config = null): static`
Get singleton instance of the model.

**Note:** Config required on first instantiation only.

### Required Abstract Methods

#### `get_post_type_args(): array`
Define post type registration arguments.

#### Optional Override Methods

#### `get_taxonomies(): array`
Define custom taxonomies.

**Taxonomy Config Formats:**
```php
// Simple format
'category_name' => 'Display Name',

// Full format
'complex_taxonomy' => [
    'labels' => [
        'name' => 'Categories',
        'singular_name' => 'Category'
    ],
    'args' => [
        'hierarchical' => true,
        'public' => true,
        'show_admin_column' => true
    ]
]
```

#### `get_admin_columns(): array|false`
Define custom admin columns.

**Column Config Options:**
- `label` (string) - Column header label
- `type` (string) - `text`, `number`, `date`, `currency`, `custom`
- `sortable` (bool|callable) - Enable sorting
- `allow_quick_edit` (bool) - Enable quick edit
- `metabox_id` (string) - MetaBox ID for data source
- `field_id` (string) - Field ID within MetaBox
- `get_value` (callable|string) - Custom value getter
- `position` (string) - `after_title`, `after_date`, `end`
- `width` (string) - Column width (`100px`, `10%`)
- `meta_key` (string) - Direct meta key override
- `currency_symbol` (string) - For currency type

### Core Methods

#### `run(bool $force_reinitialize = false): Model`
Initialize and start the model.

#### `deactivate(): Model`
Pause/deactivate the model.

#### `reactivate(): Model`
Resume the model after deactivation.

#### `is_running(): bool`
Check if model is currently active.

### MetaBox Integration

#### `register_metabox(MetaBox $metabox): Model`
Register MetaBox with the model.

#### `register_metaboxes(array $metaboxes): Model`
Register multiple MetaBoxes.

#### `getMetabox(string $metabox_id): ?MetaBox`
Get registered MetaBox by ID.

#### `getMetaboxFieldValue(int $post_id, string $metabox_id, string $field_id): mixed`
Get field value through MetaBox.

#### `updateMetaboxFieldValue(int $post_id, string $metabox_id, string $field_id, mixed $value): bool|WP_Error`
Update field value through MetaBox.

### CRUD Operations

#### `create(array $post_data, array $meta_data = [], bool $validate = true): int|WP_Error`
Create new post with metadata.

#### `update(int $post_id, array $post_data = [], array $meta_data = [], bool $validate = true): bool|WP_Error`
Update existing post.

#### `get_post(int $post_id, ?bool $strip_meta_key = true, array $config = []): ?array`
Get single post with metadata.

**Config Options:**
- `include_meta` (bool) - Include metadata (default: true)
- `include_taxonomies` (bool) - Include taxonomy terms (default: false)
- `full_taxonomies_terms` (bool) - Return full term objects (default: false)

#### `get_posts(array $args = [], ?bool $strip_meta_key = true, array $config = []): array`
Get multiple posts with metadata.

#### `get_full_post(int $post_id, ?bool $strip_meta_key = null, bool $full_taxonomies_terms = false): ?array`
Get post with all metadata and taxonomies.

#### `delete(int $post_id, bool $force_delete = false): bool|WP_Error`
Delete post and metadata.

### Search Functionality

#### `search(string $search_term, array $search_fields = ['title', 'content'], array $args = [], array $config = []): array`
Enhanced search with relevance scoring.

**Search Fields:** `title`, `content`, `meta`, `taxonomies`

#### `search_autocomplete(string $search_term, int $limit = 10, array $search_fields = ['title']): array`
Autocomplete suggestions.

#### `enqueue_search_scripts(bool $enqueue_autocomplete = true): void`
Enqueue AJAX search scripts for frontend.

### Taxonomy Management

#### `set_post_terms(int $post_id, string $taxonomy, array|string $terms, bool $append = false): array|WP_Error`
Set taxonomy terms for post.

#### `get_post_terms(int $post_id, string $taxonomy, array $args = []): array|false|WP_Error`
Get taxonomy terms for post.

### Caching

#### `set_caching(bool $enable): Model`
Enable/disable caching.

#### `set_cache_duration(int $duration): Model`
Set cache duration in seconds.

### Lifecycle Hooks

Override these methods for custom behavior:

#### `before_run(): void`
Called before model initialization.

#### `after_run(): void`
Called after model initialization.

### Implementation Guide

#### Step 1: Create Model Class

```php
class ProductModel extends Model
{
    protected const POST_TYPE = 'product';
    protected const META_PREFIX = 'product_';
    
    protected static function get_post_type_args(): array
    {
        return [
            'labels' => ['name' => 'Products', 'singular_name' => 'Product'],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail']
        ];
    }
    
    protected function get_taxonomies(): array
    {
        return [
            'product_category' => 'Product Categories',
            'product_tag' => [
                'labels' => ['name' => 'Tags'],
                'args' => ['hierarchical' => false]
            ]
        ];
    }
}
```

#### Step 2: Initialize and Register MetaBoxes

```php
$model = ProductModel::get_instance($config);

$price_metabox = MetaBox::create('product_pricing', 'Pricing', 'product', $config)
    ->add_field('price', 'Price', 'number', [], ['step' => 0.01])
    ->add_field('sale_price', 'Sale Price', 'number');

$model->register_metabox($price_metabox)->run();
```

#### Step 3: Define Admin Columns

```php
protected function get_admin_columns(): array
{
    return [
        'price' => [
            'label' => 'Price',
            'type' => 'currency',
            'currency_symbol' => '$',
            'sortable' => true,
            'metabox_id' => 'product_pricing',
            'field_id' => 'price',
            'position' => 'after_title'
        ],
        'category' => [
            'label' => 'Category',
            'type' => 'text',
            'get_value' => function($post_id) {
                $terms = get_the_terms($post_id, 'product_category');
                return $terms ? $terms[0]->name : '-';
            }
        ]
    ];
}
```

#### Step 4: Use CRUD Operations

```php
// Create product
$product_id = $model->create([
    'post_title' => 'New Product',
    'post_content' => 'Product description'
], [
    'product_price' => 29.99,
    'product_sale_price' => 24.99
]);

// Get product with metadata
$product = $model->get_full_post($product_id);

// Search products
$results = $model->search('smartphone', ['title', 'content', 'meta']);
```

### AJAX Integration

The model automatically provides AJAX endpoints:

**Search Endpoint:** `wp_ajax_{post_type}_search`
**Autocomplete Endpoint:** `wp_ajax_{post_type}_autocomplete`

#### Frontend Usage

```javascript
// Search
$.post(ajaxurl, {
    action: 'product_search',
    search: 'smartphone',
    fields: ['title', 'meta'],
    nonce: product_search_data.search_nonce
});

// Autocomplete
$.post(ajaxurl, {
    action: 'product_autocomplete',
    term: 'smart',
    limit: 5,
    nonce: product_search_data.autocomplete_nonce
});
```

### Authentication Integration

```php
protected const REQUIRES_AUTHENTICATION = true;
protected const VIEW_CAPABILITY = 'edit_products';
```

Model automatically redirects unauthenticated users and checks capabilities.

### Advanced Features

#### Custom Lifecycle Management

```php
protected function before_run(): void
{
    // Custom setup before initialization
}

protected function after_run(): void
{
    // Custom setup after initialization
}

// Pause model temporarily
$model->pause();

// Resume later
$model->resume();
```

#### State Management

```php
$state = $model->get_state();
// Returns: is_running, has_been_initialized, post_type, etc.
```

### WordPress Integration

The Model automatically:
- Registers post type on `init`
- Registers custom taxonomies
- Sets up admin columns and sorting
- Handles authentication redirects
- Provides AJAX endpoints
- Manages MetaBox saving
- Clears caches on updates

### Best Practices

1. **Singleton Usage**: Always use `get_instance()` instead of `new`
2. **MetaBox Integration**: Register MetaBoxes with model for seamless data access
3. **Admin Columns**: Define columns that match your MetaBox fields
4. **Search Fields**: Include relevant fields for better search results
5. **Caching**: Enable caching for production environments
6. **Lifecycle**: Use `before_run()` and `after_run()` for custom setup
7. **Validation**: Always validate data in CRUD operations
8. **Authentication**: Set appropriate view capabilities for sensitive content