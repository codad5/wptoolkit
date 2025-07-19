# WPToolkit

**Your go-to WordPress development library** - A comprehensive collection of utility classes that streamline WordPress plugin and theme development with modern dependency injection and service registry architecture.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.0-blue.svg)](https://wordpress.org)

## Features

🚀 **Complete architectural overhaul** with modern object-oriented design:

- **Service Registry Pattern**: Centralized dependency management across multiple applications
- **Dependency Injection**: All classes now support Config injection for better testability
- **Factory Methods**: Clean, fluent API with static factory methods
- **Multi-App Support**: Manage multiple plugins/themes from a single codebase
- **Immutable Configuration**: Type-safe, immutable configuration management
- **Enhanced Error Handling**: Comprehensive validation and error reporting

## Installation

WPToolkit can be installed in two ways:

### Method 1: Composer (Recommended)

```bash
composer require codad5/wptoolkit
```

Then use the autoloader in your plugin:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Codad5\WPToolkit\Utils\{Config, Registry, Settings, Page, Notification};
```

### Method 2: Direct Download

1. Download the `src` directory from this repository
2. Place it in your plugin directory (e.g., `./lib/wptoolkit/`)
3. Include the autoloader:

```php
<?php
require_once __DIR__ . '/lib/wptoolkit/autoloader.php';

use Codad5\WPToolkit\Utils\{Config, Registry, Settings, Page, Notification};
```

## Quick Start

WPToolkit now uses a **Service Registry** pattern for managing dependencies across your applications:

```php
<?php
use Codad5\WPToolkit\Registry;
use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification};

// 1. Create immutable configuration
$config = Config::plugin('my-awesome-plugin', __FILE__, [
    'name' => 'My Awesome Plugin',
    'version' => '1.0.0',
    'description' => 'An amazing WordPress plugin'
]);

// 2. Register your application with the registry
Registry::registerApp($config);

// 3. Create and register services
$settings = Settings::create([
    'api_key' => [
        'type' => 'text',
        'label' => 'API Key',
        'required' => true
    ]
], $config);

$page = Page::create($config);
$notification = Notification::create($config);

// 4. Register services in the registry
Registry::add($config, 'settings', $settings);
Registry::add($config, 'page', $page);
Registry::add($config, 'notification', $notification);

// 5. Use services from anywhere in your application
$settings = Registry::get('my-awesome-plugin', 'settings');
$settings->set('api_key', 'your-api-key');
```

## Core Architecture

### Service Registry

The `Registry` class is the heart of WPToolkit, providing centralized service management:

```php
// Register an application
$config = Config::plugin('my-plugin', __FILE__);
Registry::registerApp($config, [
    'settings' => Settings::create([], $config),
    'page' => Page::create($config),
    'notification' => Notification::create($config)
]);

// Retrieve services
$settings = Registry::get('my-plugin', 'settings');
$page = Registry::get('my-plugin', 'page');

// Service factories for lazy loading
Registry::factory('my-plugin', 'api', function($config) {
    return new MyAPIClient($config->get('api_key'));
});

// Service aliases for convenience
Registry::alias('my-plugin', 'notify', 'notification');
$notification = Registry::get('my-plugin', 'notify');

// Check what's registered
$stats = Registry::getStats();
$apps = Registry::getApps();
```

### Configuration Management

The `Config` class provides immutable, type-safe configuration:

```php
// For plugins
$config = Config::plugin('my-plugin', __FILE__, [
    'api_endpoint' => 'https://api.example.com',
    'cache_duration' => 3600
]);

// For themes
$config = Config::theme('my-theme', [
    'supports' => ['post-thumbnails', 'menus']
]);

// Generic applications
$config = Config::create([
    'slug' => 'my-app',
    'name' => 'My Application',
    'version' => '1.0.0'
]);

// Configuration is immutable - create new instances for changes
$updated_config = $config->with(['version' => '1.1.0']);
$subset_config = $config->only(['slug', 'name']);

// Environment detection
if ($config->isDevelopment()) {
    // Development-specific code
}

// Path and URL helpers
$asset_url = $config->url('assets/style.css');
$template_path = $config->path('templates/admin.php');
```

## Available Classes

### Settings Management

Enhanced settings management with validation, sanitization, and WordPress Settings API integration:

```php
// Create settings instance
$settings = Settings::create([
    'api_key' => [
        'type' => 'text',
        'label' => 'API Key',
        'description' => 'Enter your API key',
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field',
        'validate_callback' => function($value) {
            return strlen($value) >= 32;
        }
    ],
    'enable_features' => [
        'type' => 'checkbox',
        'label' => 'Enable Advanced Features',
        'default' => false,
        'group' => 'features'
    ],
    'theme_color' => [
        'type' => 'select',
        'label' => 'Theme Color',
        'choices' => [
            'blue' => 'Blue',
            'red' => 'Red',
            'green' => 'Green'
        ],
        'default' => 'blue',
        'group' => 'appearance'
    ],
    'cache_duration' => [
        'type' => 'number',
        'label' => 'Cache Duration (minutes)',
        'default' => 60,
        'min' => 1,
        'max' => 1440,
        'group' => 'performance'
    ]
], $config);

// Register with registry for global access
Registry::add($config, 'settings', $settings);

// Use settings
$api_key = $settings->get('api_key');
$settings->set('enable_features', true);

// Bulk operations
$all_settings = $settings->getAll();
$appearance_settings = $settings->getAll('appearance');

// Import/Export
$exported = $settings->export();
$import_results = $settings->import($data, true); // with validation

// Reset to defaults
$settings->reset('theme_color');
$settings->resetAll('performance');

// Render form fields
echo $settings->renderField('api_key');
echo $settings->renderField('theme_color', ['class' => 'custom-select']);

// Notifications integration
$settings->sendSuccessNotification('Settings saved successfully!');
$settings->sendErrorNotification('Invalid API key provided.');

// Cache management
$settings->clearCaches();
```

### Page Management

Comprehensive page management for admin and frontend pages:

```php
// Create page manager
$page = Page::create($config, '/path/to/templates');
Registry::add($config, 'page', $page);

// Add admin menu page
$page->addMenuPage('dashboard', [
    'page_title' => 'My Plugin Dashboard',
    'menu_title' => 'My Plugin',
    'capability' => 'manage_options',
    'icon' => 'dashicons-admin-tools',
    'callback' => function() {
        echo '<h1>Welcome to My Plugin</h1>';
    },
    'template' => 'admin/dashboard.php'
]);

// Add submenu pages
$page->addSubmenuPage('settings', [
    'parent_slug' => 'dashboard',
    'page_title' => 'Plugin Settings',
    'menu_title' => 'Settings',
    'template' => 'admin/settings.php'
]);

// Bulk admin page registration
$page->addAdminPages([
    'users' => [
        'parent_slug' => 'dashboard',
        'page_title' => 'Manage Users',
        'menu_title' => 'Users'
    ],
    'reports' => [
        'parent_slug' => 'dashboard',
        'page_title' => 'Reports',
        'menu_title' => 'Reports'
    ]
]);

// Frontend pages with routing
$page->addFrontendPage('profile', [
    'title' => 'User Profile',
    'template' => 'frontend/profile.php',
    'public' => true,
    'rewrite' => true,
    'capability' => 'read', // Requires user login
    'query_vars' => ['user_id']
]);

$page->addFrontendPage('public-data', [
    'title' => 'Public Data',
    'callback' => function($data) {
        echo '<h1>Public Data Page</h1>';
        // Handle the request
    },
    'public' => true
]);

// Page detection and utilities
if ($page->isPluginAdminPage('settings')) {
    // On plugin settings page
}

if ($page->isPluginFrontendPage()) {
    // On any plugin frontend page
}

$current = $page->getCurrentPage();
$slug = $page->getCurrentPageSlug();

// URL generation
$admin_url = $page->getAdminUrl('settings', ['tab' => 'api']);
$frontend_url = $page->getFrontendUrl('profile', ['user' => 123]);

// Dashboard widgets
$page->addDashboardWidget(
    'plugin-stats',
    'Plugin Statistics',
    'render_stats_widget',
    'manage_options'
);

// Template management
$page->setTemplateDirectory('/custom/template/path');
$page->renderPage('dashboard', ['user' => $current_user]);
```

### Notification System

Advanced notification system with targeting and persistence:

```php
// Create notification manager
$notification = Notification::create($config, 'My Plugin Name');
Registry::add($config, 'notification', $notification);

// Basic notifications
$notification->success('Settings saved successfully!');
$notification->error('Something went wrong.');
$notification->warning('Please update your configuration.');
$notification->info('New features available!');

// Advanced notifications with targeting
$notification->success(
    'Data imported successfully!',
    'current', // Show on current page only
    600, // Expire after 10 minutes
    true  // Dismissible
);

$notification->error(
    'API connection failed',
    'plugin', // Show on all plugin pages
    300  // Expire after 5 minutes
);

$notification->warning(
    'Maintenance mode enabled',
    'all', // Show on all admin pages
    null // No expiration
);

// Page-specific targeting
$notification->info(
    'Welcome to the dashboard!',
    ['my-plugin_dashboard', 'my-plugin_settings'] // Specific pages
);

// Notification management
$all_notifications = $notification->getNotifications();
$notification->dismiss('notification_id');
$notification->clear(); // Clear all notifications

// Static methods for global use (backward compatibility)
Notification::initGlobal(); // Call once in your main plugin file

// Then use static methods anywhere
Notification::successStatic($config, 'Operation completed!');
Notification::errorStatic($config, 'Operation failed!');

// Custom notification callback
$notification->setNotificationCallback(function($message, $type, $args) {
    // Custom notification handling
    error_log("Notification [{$type}]: {$message}");
});
```

### REST API Management

Multi-version REST API with deprecation support:

```php
// Create REST API manager
$api = RestRoute::create($config, ['v1', 'v2'], 'v1');
Registry::add($config, 'api', $api);

// Add routes with fluent interface
$api->get('v1', '/users', function($request) {
    return ['users' => get_users()];
})
->post('v1', '/users', function($request) {
    $user_data = $request->get_params();
    // Create user logic
    return $api->successResponse($user_data, 'User created');
})
->put('v1', '/users/(?P<id>\d+)', function($request) {
    $user_id = $request['id'];
    // Update user logic
    return $api->successResponse(null, 'User updated');
});

// Advanced route configuration
$api->addRoute('v1', '/products', [
    'methods' => 'POST',
    'callback' => 'create_product_callback',
    'permission_callback' => function() {
        return current_user_can('manage_options');
    },
    'args' => [
        'name' => [
            'required' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'validate_callback' => function($value) {
                return strlen($value) >= 3;
            }
        ],
        'price' => [
            'required' => true,
            'type' => 'number',
            'validate_callback' => function($value) {
                return $value > 0;
            }
        ]
    ],
    'rate_limit' => 100, // 100 requests per hour
    'cache_ttl' => 300   // Cache for 5 minutes
]);

// Version management
$api->registerVersion('v3', [
    'description' => 'Latest API version with new features',
    'changelog' => ['Added user preferences', 'Improved performance']
]);

$api->deprecateVersion('v1', '2024-01-01', '2025-01-01', 'v2');

// Copy routes between versions
$api->copyRoutes('v1', 'v2', ['/deprecated-endpoint']);

// Middleware support
$api->addGlobalMiddleware(function($request, $route_meta) {
    // Global authentication middleware
    if (!is_user_logged_in()) {
        return new WP_Error('unauthorized', 'Login required', ['status' => 401]);
    }
}, 5);

$api->addMiddleware('v2', function($request, $route_meta) {
    // Version-specific middleware
    $rate_limit = check_rate_limit($request);
    if (!$rate_limit) {
        return new WP_Error('rate_limit', 'Too many requests', ['status' => 429]);
    }
}, 10);

// Response helpers
return $api->successResponse($data, 'Operation successful', 201);
return $api->errorResponse('validation_failed', 'Invalid data provided', $errors, 400);

// Permission and nonce verification
$permission_check = $api->checkPermissions('manage_options');
$nonce_check = $api->verifyNonce($request, 'my_action');

// URL generation and API documentation
$user_url = $api->getRouteUrl('v1', '/users', ['per_page' => 20]);
$api_docs = $api->getApiDocumentation();
$available_versions = $api->getAvailableVersions(false); // Exclude deprecated
```

### File Operations

Comprehensive file and media management:

```php
// Create filesystem manager
$fs = Filesystem::create($config, ['svg', 'webp', 'pdf']);
Registry::add($config, 'filesystem', $fs);

// File operations
$content = $fs->getContents('/path/to/file.txt');
$fs->putContents('/path/to/new-file.txt', 'Hello World!', 0644);

$fs->copyFile('/source.txt', '/destination.txt', true); // Allow overwrite
$fs->moveFile('/old-location.txt', '/new-location.txt');

// Directory operations
$fs->createDirectory('/new-directory', 0755, true); // Recursive
$fs->deleteDirectory('/old-directory', true); // Recursive

// File information
$exists = $fs->fileExists('/path/to/file.txt');
$size = $fs->getFileSize('/path/to/file.txt');
$mtime = $fs->getModificationTime('/path/to/file.txt');
$permissions = $fs->getFilePermissions('/path/to/file.txt');

$file_info = $fs->getFileInfo('/path/to/file.txt');
// Returns: path, filename, extension, size, size_formatted, mime_type,
//          modified, modified_formatted, permissions, is_allowed

// Media library integration
$attachment_id = $fs->uploadToMediaLibrary($_FILES['upload'], 'Custom Title', 'Description');
$media_info = $fs->getMediaFileInfo($attachment_id);
$fs->deleteMediaFile($attachment_id, true); // Force delete

// Plugin-specific uploads
$upload_dir = $fs->createAppUploadDir('documents');
$unique_name = $fs->getUniqueFilename('document.pdf', 'documents');

// File type management
$fs->addAllowedFileTypes(['ai', 'eps', 'psd']);
$allowed_types = $fs->getAllowedFileTypes();
$is_allowed = $fs->isAllowedFileType('pdf');
$mime_types = $fs->getAllowedMimeTypes();

// Directory scanning
$files = $fs->scanDirectory('/scan/directory', true, ['jpg', 'png']); // Recursive, filter by extensions

// Utilities
$formatted_size = $fs->formatFileSize(1024000); // "1 MB"
$mime_type = $fs->getMimeType('/path/to/image.jpg');
```

### Development & Debugging

Enhanced debugging with console integration and performance monitoring:

```php
// Initialize debugger for your app
Debugger::init('my-plugin', true, 'My Plugin', 'my-plugin-textdomain');

// Or initialize from config
Debugger::initFromConfig($config);

// Console logging (appears in browser console)
Debugger::console('my-plugin', 'Debug message');
Debugger::info('my-plugin', 'Information message');
Debugger::warn('my-plugin', 'Warning message');
Debugger::error('my-plugin', 'Error message');

// Variable inspection
$data = ['key' => 'value', 'number' => 42];
Debugger::printR('my-plugin', $data);
Debugger::varDump('my-plugin', $data);
Debugger::printR('my-plugin', $data, 'Custom stop message'); // Dies after output

// Interactive breakpoints
Debugger::breakpoint('my-plugin', 'Checking user data', [
    'user_id' => $user_id,
    'request_data' => $_POST
]);

// Performance monitoring
$start = Debugger::timer('my-plugin', 'Database Query');
// ... database operation
Debugger::timer('my-plugin', 'Database Query', $start); // Logs duration

// WordPress-specific debugging
Debugger::queryInfo('my-plugin'); // Current WP_Query information

// Admin notifications for debugging
Debugger::notification('my-plugin', 'Debug info displayed', 'warning');

// Development helpers
Debugger::sleep('my-plugin', 2); // Only sleeps in dev mode

// Error logging
Debugger::log('my-plugin', 'Custom log message', 'ERROR');
Debugger::log('my-plugin', $complex_data, 'DEBUG');

// Instance management
$is_dev = Debugger::isDev('my-plugin');
$instances = Debugger::getInstances();
$has_instance = Debugger::hasInstance('my-plugin');
```

## Complete Plugin Example

Here's a comprehensive example showing WPToolkit in action:

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: A WordPress plugin built with WPToolkit
 * Version: 1.0.0
 * Requires PHP: 8.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include WPToolkit
require_once __DIR__ . '/vendor/autoload.php';

use Codad5\WPToolkit\Utils\{Config, Registry, Settings, Page, Notification, RestRoute, Filesystem, Debugger};

class MyAwesomePlugin {

    private Config $config;
    private string $app_slug = 'my-awesome-plugin';

    public function __construct() {
        $this->init_config();
        $this->register_services();
        $this->setup_hooks();
    }

    private function init_config(): void {
        // Create immutable configuration
        $this->config = Config::plugin($this->app_slug, __FILE__, [
            'name' => 'My Awesome Plugin',
            'version' => '1.0.0',
            'description' => 'An amazing WordPress plugin built with WPToolkit',
            'text_domain' => 'my-awesome-plugin',
            'api_endpoint' => 'https://api.example.com',
            'cache_duration' => 3600
        ]);

        // Register application with service registry
        Registry::registerApp($this->config);

        // Initialize debugging in development
        if ($this->config->isDevelopment()) {
            Debugger::initFromConfig($this->config);
            Debugger::info($this->app_slug, 'Plugin initialized in development mode');
        }
    }

    private function register_services(): void {
        // Settings service
        $settings = Settings::create([
            'api_key' => [
                'type' => 'password',
                'label' => 'API Key',
                'description' => 'Enter your API key from the service dashboard',
                'required' => true,
                'group' => 'api'
            ],
            'api_timeout' => [
                'type' => 'number',
                'label' => 'API Timeout (seconds)',
                'default' => 30,
                'min' => 5,
                'max' => 120,
                'group' => 'api'
            ],
            'enable_caching' => [
                'type' => 'checkbox',
                'label' => 'Enable Response Caching',
                'default' => true,
                'group' => 'performance'
            ],
            'cache_duration' => [
                'type' => 'number',
                'label' => 'Cache Duration (minutes)',
                'default' => 60,
                'min' => 1,
                'max' => 1440,
                'group' => 'performance'
            ],
            'theme_color' => [
                'type' => 'select',
                'label' => 'Admin Theme Color',
                'choices' => [
                    'blue' => 'Blue',
                    'green' => 'Green',
                    'purple' => 'Purple',
                    'orange' => 'Orange'
                ],
                'default' => 'blue',
                'group' => 'appearance'
            ]
        ], $this->config);

        // Page service
        $page = Page::create($this->config, plugin_dir_path(__FILE__) . 'templates');

        // Notification service
        $notification = Notification::create($this->config);

        // REST API service
        $api = RestRoute::create($this->config, ['v1'], 'v1');

        // Filesystem service
        $filesystem = Filesystem::create($this->config, ['svg', 'webp']);

        // Register all services
        Registry::addMany($this->config, [
            'settings' => $settings,
            'page' => $page,
            'notification' => $notification,
            'api' => $api,
            'filesystem' => $filesystem
        ]);

        // Register service aliases for convenience
        Registry::aliases($this->config, [
            'notify' => 'notification',
            'fs' => 'filesystem'
        ]);

        // Setup lazy-loaded services
        Registry::factory($this->config, 'api_client', function($config) {
            $settings = Registry::get($this->app_slug, 'settings');
            return new MyAPIClient($settings->get('api_key'));
        });

        $this->setup_pages();
        $this->setup_api_routes();
    }

    private function setup_pages(): void {
        /** @var Page $page */
        $page = Registry::get($this->app_slug, 'page');

        // Main dashboard
        $page->addMenuPage('dashboard', [
            'page_title' => 'My Awesome Plugin',
            'menu_title' => 'Awesome Plugin',
            'icon' => 'dashicons-admin-tools',
            'callback' => [$this, 'render_dashboard']
        ]);

        // Settings page
        $page->addSubmenuPage('settings', [
            'parent_slug' => 'dashboard',
            'page_title' => 'Plugin Settings',
            'menu_title' => 'Settings',
            'callback' => [$this, 'render_settings']
        ]);

        // Tools page
        $page->addSubmenuPage('tools', [
            'parent_slug' => 'dashboard',
            'page_title' => 'Plugin Tools',
            'menu_title' => 'Tools',
            'callback' => [$this, 'render_tools']
        ]);

        // Frontend page
        $page->addFrontendPage('user-dashboard', [
            'title' => 'User Dashboard',
            'template' => 'frontend/dashboard.php',
            'capability' => 'read' // Requires login
        ]);

        // Dashboard widget
        $page->addDashboardWidget(
            'plugin-stats',
            'Plugin Statistics',
            [$this, 'render_dashboard_widget']
        );
    }

    private function setup_api_routes(): void {
        /** @var RestRoute $api */
        $api = Registry::get($this->app_slug, 'api');

        // Status endpoint
        $api->get('v1', '/status', function($request) {
            return [
                'plugin' => $this->config->get('name'),
                'version' => $this->config->get('version'),
                'status' => 'active',
                'timestamp' => current_time('mysql')
            ];
        });

        // Settings endpoints
        $api->get('v1', '/settings', [$this, 'api_get_settings']);
        $api->post('v1', '/settings', [$this, 'api_update_settings']);

        // File upload endpoint
        $api->post('v1', '/upload', [
            'callback' => [$this, 'api_upload_file'],
            'permission_callback' => function() {
                return current_user_can('upload_files');
            },
            'args' => [
                'title' => [
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }

    private function setup_hooks(): void {
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_enqueue_scripts']);
        add_action('admin_notices', [$this, 'admin_notices']);

        // Plugin activation/deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    public function admin_init(): void {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        /** @var Page $page */
        $page = Registry::get($this->app_slug, 'page');

        // Check if API key is configured
        $api_key = $settings->get('api_key');
        if (empty($api_key) && $page->isPluginAdminPage()) {
            /** @var Notification $notification */
            $notification = Registry::get($this->app_slug, 'notification');
            $notification->warning(
                'Please configure your API key in the <a href="' .
                $page->getAdminUrl('settings') . '">settings page</a>.',
                'plugin'
            );
        }
    }

    public function render_dashboard(): void {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        $data = [
            'plugin_name' => $this->config->get('name'),
            'version' => $this->config->get('version'),
            'api_configured' => !empty($settings->get('api_key')),
            'cache_enabled' => $settings->get('enable_caching'),
            'stats' => $this->get_plugin_stats()
        ];

        $this->render_template('admin/dashboard.php', $data);
    }

    public function render_settings(): void {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        // Handle form submission
        if ($_POST && wp_verify_nonce($_POST['_wpnonce'], 'update_settings')) {
            $this->handle_settings_update();
        }

        $data = [
            'settings' => $settings,
            'groups' => $settings->getGroups(),
            'current_tab' => $_GET['tab'] ?? 'api'
        ];

        $this->render_template('admin/settings.php', $data);
    }

    public function render_tools(): void {
        $data = [
            'tools' => [
                'clear_cache' => 'Clear Plugin Cache',
                'test_api' => 'Test API Connection',
                'export_settings' => 'Export Settings',
                'import_settings' => 'Import Settings'
            ]
        ];

        $this->render_template('admin/tools.php', $data);
    }

    public function render_dashboard_widget(): void {
        $stats = $this->get_plugin_stats();
        echo '<div class="plugin-widget">';
        echo '<p><strong>API Calls Today:</strong> ' . esc_html($stats['api_calls']) . '</p>';
        echo '<p><strong>Cache Hit Rate:</strong> ' . esc_html($stats['cache_rate']) . '%</p>';
        echo '</div>';
    }

    // API Endpoints
    public function api_get_settings($request): array {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        $group = $request->get_param('group');
        return $settings->getAll($group);
    }

    public function api_update_settings($request) {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        /** @var RestRoute $api */
        $api = Registry::get($this->app_slug, 'api');

        $data = $request->get_json_params();
        $results = [];

        foreach ($data as $key => $value) {
            $results[$key] = $settings->set($key, $value);
        }

        if (in_array(false, $results, true)) {
            return $api->errorResponse('validation_failed', 'Some settings could not be updated', $results);
        }

        return $api->successResponse($results, 'Settings updated successfully');
    }

    public function api_upload_file($request) {
        /** @var Filesystem $fs */
        $fs = Registry::get($this->app_slug, 'filesystem');

        /** @var RestRoute $api */
        $api = Registry::get($this->app_slug, 'api');

        if (empty($_FILES['file'])) {
            return $api->errorResponse('no_file', 'No file uploaded');
        }

        $title = $request->get_param('title') ?: 'Uploaded File';
        $attachment_id = $fs->uploadToMediaLibrary($_FILES['file'], $title);

        if (!$attachment_id) {
            return $api->errorResponse('upload_failed', 'File upload failed');
        }

        $file_info = $fs->getMediaFileInfo($attachment_id);
        return $api->successResponse($file_info, 'File uploaded successfully');
    }

    // Helper methods
    private function handle_settings_update(): void {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        /** @var Notification $notification */
        $notification = Registry::get($this->app_slug, 'notification');

        $updated = 0;
        foreach ($_POST as $key => $value) {
            if ($key !== '_wpnonce' && $settings->getSettingConfig($key)) {
                if ($settings->set($key, $value)) {
                    $updated++;
                }
            }
        }

        if ($updated > 0) {
            $notification->success("Updated {$updated} setting(s) successfully.");
        } else {
            $notification->warning('No settings were updated.');
        }
    }

    private function get_plugin_stats(): array {
        return [
            'api_calls' => get_option($this->app_slug . '_api_calls_today', 0),
            'cache_rate' => get_option($this->app_slug . '_cache_hit_rate', 0),
            'users_count' => count(get_users(['meta_key' => $this->app_slug . '_user'])),
            'last_sync' => get_option($this->app_slug . '_last_sync', 'Never')
        ];
    }

    private function render_template(string $template, array $data = []): void {
        $template_path = plugin_dir_path(__FILE__) . 'templates/' . $template;

        if (file_exists($template_path)) {
            extract($data);
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>Template not found: ' . esc_html($template) . '</p></div>';
        }
    }

    public function admin_enqueue_scripts($hook): void {
        /** @var Page $page */
        $page = Registry::get($this->app_slug, 'page');

        if (!$page->isPluginAdminPage()) {
            return;
        }

        wp_enqueue_style(
            $this->app_slug . '-admin',
            $this->config->url('assets/css/admin.css'),
            [],
            $this->config->get('version')
        );

        wp_enqueue_script(
            $this->app_slug . '-admin',
            $this->config->url('assets/js/admin.js'),
            ['jquery'],
            $this->config->get('version'),
            true
        );

        // Localize script with API data
        /** @var RestRoute $api */
        $api = Registry::get($this->app_slug, 'api');

        wp_localize_script($this->app_slug . '-admin', 'pluginAPI', [
            'root' => esc_url_raw(rest_url($api->getFullNamespace('v1'))),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    public function frontend_enqueue_scripts(): void {
        /** @var Page $page */
        $page = Registry::get($this->app_slug, 'page');

        if (!$page->isPluginFrontendPage()) {
            return;
        }

        wp_enqueue_style(
            $this->app_slug . '-frontend',
            $this->config->url('assets/css/frontend.css'),
            [],
            $this->config->get('version')
        );
    }

    public function admin_notices(): void {
        // Global notification display is handled by Notification::initGlobal()
        // This is called once in the main plugin file
    }

    public function activate(): void {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        // Set default options
        $defaults = [
            'api_timeout' => 30,
            'enable_caching' => true,
            'cache_duration' => 60,
            'theme_color' => 'blue'
        ];

        foreach ($defaults as $key => $value) {
            if ($settings->get($key) === null) {
                $settings->set($key, $value);
            }
        }

        // Create upload directory
        /** @var Filesystem $fs */
        $fs = Registry::get($this->app_slug, 'filesystem');
        $fs->createAppUploadDir();

        // Flush rewrite rules for frontend pages
        flush_rewrite_rules();

        Debugger::log($this->app_slug, 'Plugin activated successfully');
    }

    public function deactivate(): void {
        // Clean up temporary data
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');
        $settings->clearCaches();

        // Flush rewrite rules
        flush_rewrite_rules();

        Debugger::log($this->app_slug, 'Plugin deactivated');
    }
}

// Initialize global notification system (call once)
Notification::initGlobal();

// Initialize the plugin
new MyAwesomePlugin();
```

## Template Examples

### Admin Dashboard Template (`templates/admin/dashboard.php`)

```php
<div class="wrap <?php echo esc_attr($app_slug); ?>-dashboard">
    <h1><?php echo esc_html($plugin_name); ?> Dashboard</h1>

    <div class="dashboard-widgets-wrap">
        <div class="metabox-holder">
            <div class="postbox-container" style="width: 65%;">
                <div class="postbox">
                    <h2 class="hndle">Quick Stats</h2>
                    <div class="inside">
                        <table class="wp-list-table widefat">
                            <tbody>
                                <tr>
                                    <td><strong>Plugin Version:</strong></td>
                                    <td><?php echo esc_html($version); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>API Status:</strong></td>
                                    <td>
                                        <span class="status-indicator <?php echo $api_configured ? 'connected' : 'disconnected'; ?>">
                                            <?php echo $api_configured ? 'Connected' : 'Not Configured'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Cache Status:</strong></td>
                                    <td><?php echo $cache_enabled ? 'Enabled' : 'Disabled'; ?></td>
                                </tr>
                                <tr>
                                    <td><strong>API Calls Today:</strong></td>
                                    <td><?php echo esc_html($stats['api_calls']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="postbox-container" style="width: 35%;">
                <div class="postbox">
                    <h2 class="hndle">Quick Actions</h2>
                    <div class="inside">
                        <p><a href="<?php echo esc_url($page->getAdminUrl('settings')); ?>" class="button button-primary">Configure Settings</a></p>
                        <p><a href="<?php echo esc_url($page->getAdminUrl('tools')); ?>" class="button">Access Tools</a></p>
                        <p><a href="<?php echo esc_url($api->getRouteUrl('v1', '/status')); ?>" class="button" target="_blank">View API Status</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Settings Template (`templates/admin/settings.php`)

```php
<div class="wrap <?php echo esc_attr($app_slug); ?>-settings">
    <h1>Plugin Settings</h1>

    <nav class="nav-tab-wrapper">
        <?php foreach ($groups as $group): ?>
        <a href="?page=<?php echo esc_attr($_GET['page']); ?>&tab=<?php echo esc_attr($group); ?>"
           class="nav-tab <?php echo $current_tab === $group ? 'nav-tab-active' : ''; ?>">
            <?php echo esc_html(ucfirst($group)); ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="">
        <?php wp_nonce_field('update_settings'); ?>

        <table class="form-table" role="presentation">
            <?php
            $group_settings = $settings->getSettingsConfig($current_tab);
            foreach ($group_settings as $key => $config):
            ?>
            <tr>
                <th scope="row">
                    <label for="<?php echo esc_attr($key); ?>">
                        <?php echo esc_html($config['label']); ?>
                        <?php if ($config['required'] ?? false): ?>
                        <span class="required">*</span>
                        <?php endif; ?>
                    </label>
                </th>
                <td>
                    <?php echo $settings->renderField($key); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php submit_button(); ?>
    </form>
</div>
```

## Advanced Usage Patterns

### Service Factories and Lazy Loading

```php
// Register expensive services as factories
Registry::factory($config, 'ai_service', function($config) {
    return new ExpensiveAIService($config->get('ai_api_key'));
});

Registry::factory($config, 'database', function($config) {
    return new CustomDatabase($config->get('db_config'));
});

// Services are only instantiated when first accessed
$ai_service = Registry::get('my-app', 'ai_service'); // Created here
$ai_service = Registry::get('my-app', 'ai_service'); // Reuses same instance
```

### Multi-App Management

```php
// Manage multiple plugins/themes from one codebase
$main_plugin_config = Config::plugin('main-plugin', __FILE__);
$addon_config = Config::plugin('addon-plugin', __FILE__, ['parent' => 'main-plugin']);

Registry::registerApp($main_plugin_config, [
    'settings' => Settings::create($main_settings, $main_plugin_config)
]);

Registry::registerApp($addon_config, [
    'settings' => Settings::create($addon_settings, $addon_config)
]);

// Cross-app service access
$main_settings = Registry::get('main-plugin', 'settings');
$addon_settings = Registry::get('addon-plugin', 'settings');

// Get all registered apps
$all_apps = Registry::getApps();
$registry_stats = Registry::getStats();
```

### Configuration Inheritance

```php
// Base configuration
$base_config = Config::create([
    'slug' => 'my-plugin',
    'version' => '1.0.0',
    'cache_enabled' => true
]);

// Environment-specific configurations
$dev_config = $base_config->with([
    'debug' => true,
    'cache_enabled' => false,
    'api_endpoint' => 'https://dev-api.example.com'
]);

$prod_config = $base_config->with([
    'debug' => false,
    'api_endpoint' => 'https://api.example.com'
]);

// Use appropriate config based on environment
$config = WP_DEBUG ? $dev_config : $prod_config;
```

## Best Practices

### 1. Configuration Management

```php
// Always create configuration first
$config = Config::plugin('my-plugin', __FILE__, [
    'name' => 'My Plugin',
    'version' => '1.0.0'
]);

// Register with registry immediately
Registry::registerApp($config);
```

### 2. Service Registration

```php
// Register all services at once for better organization
Registry::addMany($config, [
    'settings' => Settings::create($settings_config, $config),
    'page' => Page::create($config),
    'notification' => Notification::create($config)
]);

// Use aliases for commonly accessed services
Registry::aliases($config, [
    'notify' => 'notification',
    'fs' => 'filesystem'
]);
```

### 3. Error Handling

```php
// Always handle service retrieval errors
$settings = Registry::get('my-plugin', 'settings');
if (!$settings) {
    wp_die('Settings service not registered');
}

// Or use in try-catch blocks
try {
    $api_result = $external_api->call();
    Registry::get('my-plugin', 'notify')->success('API call successful');
} catch (Exception $e) {
    Registry::get('my-plugin', 'notify')->error('API call failed: ' . $e->getMessage());
    Debugger::error('my-plugin', 'API Error', ['exception' => $e]);
}
```

### 4. Development vs Production

```php
// Use config-based environment detection
if ($config->isDevelopment()) {
    Debugger::initFromConfig($config);
    // Development-specific code
} else {
    // Production optimizations
}
```

## Migration from v1.x

If you're upgrading from WPToolkit v1.x, here are the key changes:

### Old (v1.x)

```php
Config::init(['slug' => 'my-plugin']);
Settings::init($settings_config);
Page::init();
$value = Settings::get('api_key');
```

### New (v2.x)

```php
$config = Config::plugin('my-plugin', __FILE__);
Registry::registerApp($config, [
    'settings' => Settings::create($settings_config, $config),
    'page' => Page::create($config)
]);
$settings = Registry::get('my-plugin', 'settings');
$value = $settings->get('api_key');
```

## Requirements

- **PHP 8.1** or higher (uses modern PHP features like union types, readonly properties)
- **WordPress 5.0** or higher
- Modern browser support for console debugging features

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/codad5/wptoolkit/issues) on GitHub.

---

**WPToolkit** - Modern WordPress development with dependency injection, service registry, and enterprise-grade architecture! 🚀
