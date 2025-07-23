# WPToolkit

**Your go-to WordPress development library** - A comprehensive collection of utility classes that streamline WordPress plugin and theme development with modern dependency injection, service registry architecture, and advanced form management.

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
- **Advanced Form Management**: MetaBox and InputValidator with lifecycle hooks
- **Smart Caching**: Multi-level caching with automatic invalidation
- **Template System**: Flexible view loading with inheritance support
- **Database Models**: Abstract model class with CRUD operations and caching

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

use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification, Cache, ViewLoader, InputValidator};
use Codad5\WPToolkit\DB\{Model, MetaBox};
```

### Method 2: Direct Download

1. Download the `src` directory from this repository
2. Place it in your plugin directory (e.g., `./lib/wptoolkit/`)
3. Include the autoloader:

```php
<?php
require_once __DIR__ . '/lib/wptoolkit/autoloader.php';

use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification, Cache, ViewLoader, InputValidator};
use Codad5\WPToolkit\DB\{Model, MetaBox};
```

## Quick Start

WPToolkit uses a **Service Registry** pattern for managing dependencies across your applications:

```php
<?php
use Codad5\WPToolkit\Registry;
use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification, Cache, ViewLoader, InputValidator};
use Codad5\WPToolkit\DB\{Model, MetaBox};

// 1. Create immutable configuration
$config = Config::plugin('my-awesome-plugin', __FILE__, [
    'name' => 'My Awesome Plugin',
    'version' => '1.0.0',
    'description' => 'An amazing WordPress plugin built with WPToolkit'
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

// 4. Create MetaBox for custom fields
$metabox = MetaBox::create('product_details', 'Product Details', 'product', $config)
    ->add_field('price', 'Price', 'number', [], ['min' => 0, 'step' => 0.01])
    ->add_field('category', 'Category', 'select', [
        'electronics' => 'Electronics',
        'clothing' => 'Clothing',
        'books' => 'Books'
    ])
    ->onSuccess(function($post_id, $metabox) {
        // Custom success handling
        wp_cache_delete('product_' . $post_id, 'products');
    })
    ->setup_actions();

// 5. Register services in the registry
Registry::addMany($config, [
    'settings' => $settings,
    'page' => $page,
    'notification' => $notification,
    'product_metabox' => $metabox
]);

// 6. Use services from anywhere in your application
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

### Cache Management

Advanced caching utility with group-based organization:

```php
use Codad5\WPToolkit\Utils\Cache;

// Basic caching
Cache::set('user_data', $user_data, 3600, 'users'); // 1 hour in 'users' group
$user_data = Cache::get('user_data', [], 'users');

// Remember pattern - cache or compute
$expensive_data = Cache::remember('complex_query', function() {
    return perform_complex_database_query();
}, 1800, 'database'); // 30 minutes

// Bulk operations
Cache::set_many([
    'key1' => 'value1',
    'key2' => 'value2'
], 3600, 'bulk_data');

$values = Cache::get_many(['key1', 'key2'], 'default', 'bulk_data');

// Group management
Cache::clear_group('users'); // Clear all user-related cache
$stats = Cache::get_stats('users'); // Get cache statistics for group

// Advanced operations
Cache::flush_expired('users'); // Remove expired entries
$all_cache = Cache::list_group('users'); // List all cached data in group
```

### Form Management

Advanced MetaBox and input validation system:

```php
use Codad5\WPToolkit\Forms\{MetaBox, InputValidator};

// Create MetaBox with advanced features
$metabox = MetaBox::create('event_details', 'Event Details', 'event', $config)
    ->set_caching(true, 7200) // Enable caching for 2 hours
    ->add_field('event_date', 'Event Date', 'date', [], [
        'required' => true,
        'min' => date('Y-m-d'), // No past dates
    ], [
        'allow_quick_edit' => true,
        'description' => 'Select the event date'
    ])
    ->add_field('ticket_price', 'Ticket Price', 'number', [], [
        'min' => 0,
        'step' => 0.01,
        'required' => true
    ], [
        'sanitize_callback' => function($value) {
            return round(floatval($value), 2);
        }
    ])
    ->add_field('venue', 'Venue', 'select', [
        'auditorium' => 'Main Auditorium',
        'conference' => 'Conference Hall',
        'outdoor' => 'Outdoor Space'
    ], ['required' => true])
    ->add_field('featured_image', 'Featured Image', 'wp_media', [], [
        'multiple' => false
    ], [
        'description' => 'Upload event featured image'
    ])
    // Lifecycle callbacks
    ->onPreSave(function($post_id, $metabox) {
        // Custom logic before saving
        error_log("About to save event details for post {$post_id}");
    })
    ->onSuccess(function($post_id, $metabox) {
        // Clear related caches
        wp_cache_delete("event_details_{$post_id}", 'events');
        
        // Send notification
        $notification = Registry::get('my-plugin', 'notification');
        $notification->success('Event details saved successfully!');
    })
    ->onError(function($errors, $post_id, $metabox) {
        // Handle validation errors
        error_log("Validation failed for post {$post_id}: " . print_r($errors, true));
        
        $notification = Registry::get('my-plugin', 'notification');
        $notification->error('Please fix the errors and try again.');
    })
    ->setup_actions();

// Custom validation with InputValidator
InputValidator::register_validator('custom_email', function($value, $field) {
    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address';
    }
    
    // Custom domain check
    $domain = substr(strrchr($value, "@"), 1);
    if (in_array($domain, ['spam.com', 'fake.com'])) {
        return 'Email domain not allowed';
    }
    
    return true;
});

// Global validator for all fields
InputValidator::add_global_validator(function($value, $field, $type) {
    // Block certain words across all fields
    $blocked_words = ['spam', 'fake', 'test123'];
    
    if (is_string($value)) {
        foreach ($blocked_words as $word) {
            if (stripos($value, $word) !== false) {
                return "Content contains blocked word: {$word}";
            }
        }
    }
    
    return true;
});

// Custom error messages
InputValidator::set_error_message('required', 'This field is absolutely required!');
InputValidator::set_error_message('invalid_email', 'Please provide a valid email address.');

// Bulk validation
$validation_results = InputValidator::validate_many([
    'email' => ['email', 'user@example.com'],
    'age' => ['number', '25'],
    'name' => ['text', 'John Doe']
], [
    'email' => ['required' => true],
    'age' => ['attributes' => ['min' => 18, 'max' => 100]],
    'name' => ['required' => true, 'attributes' => ['minlength' => 2]]
]);
```

### Database Models

Abstract model class for custom post types with MetaBox integration:

```php
use Codad5\WPToolkit\DB\Model;
use Codad5\WPToolkit\Forms\MetaBox;

class EventModel extends Model
{
    protected const POST_TYPE = 'event';
    
    protected static function get_post_type_args(): array
    {
        return [
            'labels' => [
                'name' => 'Events',
                'singular_name' => 'Event',
                'add_new' => 'Add New Event',
                'edit_item' => 'Edit Event'
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true
        ];
    }
    
    public function __construct(Config $config)
    {
        parent::__construct($config);
        $this->setup_metaboxes();
        $this->cache_duration = 3600; // 1 hour cache
    }
    
    private function setup_metaboxes(): void
    {
        $event_details = MetaBox::create('event_details', 'Event Details', static::POST_TYPE, $this->config)
            ->add_field('start_date', 'Start Date', 'date', [], ['required' => true])
            ->add_field('end_date', 'End Date', 'date', [], ['required' => true])
            ->add_field('max_attendees', 'Max Attendees', 'number', [], ['min' => 1])
            ->add_field('venue_name', 'Venue Name', 'text', [], ['required' => true])
            ->add_field('ticket_price', 'Ticket Price', 'number', [], ['min' => 0, 'step' => 0.01])
            ->onSuccess(function($post_id) {
                // Clear cache when event is updated
                $this->clear_post_cache($post_id);
            })
            ->setup_actions();
            
        $this->register_metabox($event_details);
    }
    
    // Custom methods for event-specific operations
    public function get_upcoming_events(int $limit = 10): array
    {
        return $this->get_posts([
            'meta_query' => [
                [
                    'key' => 'event_details_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>='
                ]
            ],
            'posts_per_page' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => 'event_details_event_start_date',
            'order' => 'ASC'
        ], true);
    }
    
    public function get_events_by_venue(string $venue): array
    {
        return $this->get_posts([
            'meta_query' => [
                [
                    'key' => 'event_details_event_venue_name',
                    'value' => $venue,
                    'compare' => 'LIKE'
                ]
            ]
        ], true);
    }
}

// Usage
$config = Config::plugin('event-manager', __FILE__);
$event_model = new EventModel($config);

// Create new event
$event_id = $event_model->create([
    'post_title' => 'WordPress Conference 2024',
    'post_content' => 'Join us for an amazing WordPress conference...',
    'post_status' => 'publish'
], [
    'event_details_event_start_date' => '2024-06-15',
    'event_details_event_end_date' => '2024-06-17',
    'event_details_event_max_attendees' => 500,
    'event_details_event_venue_name' => 'Convention Center',
    'event_details_event_ticket_price' => 199.99
]);

// Get upcoming events
$upcoming = $event_model->get_upcoming_events(5);

// Search events
$results = $event_model->search('WordPress', ['title', 'content', 'meta']);

// Get statistics
$stats = $event_model->get_statistics();
```

### View Loading System

Advanced template management with inheritance and caching:

```php
use Codad5\WPToolkit\Utils\ViewLoader;

// Setup view paths with priority
ViewLoader::add_path(get_template_directory() . '/wptoolkit-templates', 5); // Theme override
ViewLoader::add_path($config->path('templates'), 10); // Plugin templates
ViewLoader::add_path($config->path('views'), 15); // Fallback views

// Enable caching for production
if (!$config->isDevelopment()) {
    ViewLoader::enable_cache(3600, 'plugin_views');
}

// Set global data available to all templates
ViewLoader::set_global_data([
    'plugin_name' => $config->get('name'),
    'plugin_version' => $config->get('version'),
    'current_user' => wp_get_current_user(),
    'site_url' => home_url()
]);

// Load templates
ViewLoader::load('admin/dashboard', [
    'stats' => $dashboard_stats,
    'recent_posts' => $recent_posts
]);

// Template inheritance with sections
ViewLoader::layout('layouts/admin', 'admin/settings', [
    'settings' => $settings_data,
    'page_title' => 'Plugin Settings'
]);

// Check if template exists
if (ViewLoader::exists('custom/special-template')) {
    ViewLoader::load('custom/special-template', $data);
} else {
    ViewLoader::load('fallback/default-template', $data);
}

// Load without echoing
$html_content = ViewLoader::get('email/notification', [
    'user' => $user,
    'message' => $notification_message
]);

// Clear template cache
ViewLoader::clear_cache();
```

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

## Complete Plugin Example with Forms

Here's a comprehensive example showing WPToolkit with Forms integration:

```php
<?php
/**
 * Plugin Name: Event Manager Pro
 * Description: Advanced event management with WPToolkit
 * Version: 1.0.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Codad5\WPToolkit\Utils\{Config, Registry, Settings, Page, Notification, Cache, ViewLoader, Debugger};
use Codad5\WPToolkit\Forms\{MetaBox, InputValidator};
use Codad5\WPToolkit\DB\Model;

class EventManagerPro {
    private Config $config;
    private string $app_slug = 'event-manager-pro';

    public function __construct() {
        $this->init_config();
        $this->setup_view_loader();
        $this->register_services();
        $this->setup_hooks();
    }

    private function init_config(): void {
        $this->config = Config::plugin($this->app_slug, __FILE__, [
            'name' => 'Event Manager Pro',
            'version' => '1.0.0',
            'description' => 'Advanced event management system',
            'cache_enabled' => true,
            'cache_duration' => 3600
        ]);

        Registry::registerApp($this->config);

        if ($this->config->isDevelopment()) {
            Debugger::initFromConfig($this->config);
        }
    }

    private function setup_view_loader(): void {
        ViewLoader::add_path($this->config->path('templates'), 10);
        
        if (!$this->config->isDevelopment()) {
            ViewLoader::enable_cache(3600, 'event_manager_views');
        }

        ViewLoader::set_global_data([
            'plugin_name' => $this->config->get('name'),
            'plugin_version' => $this->config->get('version'),
            'plugin_url' => $this->config->url(),
            'assets_url' => $this->config->url('assets')
        ]);
    }

    private function register_services(): void {
        // Settings
        $settings = Settings::create([
            'default_venue' => [
                'type' => 'text',
                'label' => 'Default Venue',
                'description' => 'Default venue for new events',
                'group' => 'defaults'
            ],
            'max_attendees_limit' => [
                'type' => 'number',
                'label' => 'Maximum Attendees Limit',
                'default' => 1000,
                'min' => 1,
                'group' => 'limits'
            ],
            'enable_email_notifications' => [
                'type' => 'checkbox',
                'label' => 'Enable Email Notifications',
                'default' => true,
                'group' => 'notifications'
            ]
        ], $this->config);

        // Page manager
        $page = Page::create($this->config);

        // Notification system
        $notification = Notification::create($this->config);

        // Event model with MetaBoxes
        $event_model = new EventModel($this->config);

        // Register services
        Registry::addMany($this->config, [
            'settings' => $settings,
            'page' => $page,
            'notification' => $notification,
            'event_model' => $event_model
        ]);

        $this->setup_admin_pages();
        $this->setup_custom_validators();
    }

    private function setup_admin_pages(): void {
        /** @var Page $page */
        $page = Registry::get($this->app_slug, 'page');

        $page->addMenuPage('dashboard', [
            'page_title' => 'Event Manager Dashboard',
            'menu_title' => 'Event Manager',
            'icon' => 'dashicons-calendar-alt',
            'callback' => [$this, 'render_dashboard']
        ]);

        $page->addSubmenuPage('settings', [
            'parent_slug' => 'dashboard',
            'page_title' => 'Event Settings',
            'menu_title' => 'Settings',
            'callback' => [$this, 'render_settings']
        ]);
    }

    private function setup_custom_validators(): void {
        // Custom validation for event dates
        InputValidator::register_validator('future_date', function($value, $field) {
            if (strtotime($value) <= time()) {
                return 'Event date must be in the future';
            }
            return true;
        });

        // Global validator to prevent past dates
        InputValidator::add_global_validator(function($value, $field, $type) {
            if ($type === 'date' && isset($field['no_past_dates']) && $field['no_past_dates']) {
                if (strtotime($value) < strtotime('today')) {
                    return 'Past dates are not allowed';
                }
            }
            return true;
        });
    }

    public function render_dashboard(): void {
        /** @var EventModel $event_model */
        $event_model = Registry::get($this->app_slug, 'event_model');

        $data = [
            'total_events' => $event_model->get_posts(['posts_per_page' => -1], false),
            'upcoming_events' => $event_model->get_upcoming_events(5),
            'stats' => $event_model->get_statistics()
        ];

        ViewLoader::load('admin/dashboard', $data);
    }

    public function render_settings(): void {
        /** @var Settings $settings */
        $settings = Registry::get($this->app_slug, 'settings');

        if ($_POST && wp_verify_nonce($_POST['_wpnonce'], 'update_settings')) {
            $this->handle_settings_update();
        }

        ViewLoader::load('admin/settings', [
            'settings' => $settings,
            'groups' => $settings->getGroups()
        ]);
    }

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
        }
    }

    private function setup_hooks(): void {
        add_action('init', function() {
            /** @var EventModel $event_model */
            $event_model = Registry::get($this->app_slug, 'event_model');
            $event_model->register_post_type();
        });

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
    }

    public function admin_enqueue_scripts(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'event') {
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
    }
}

class EventModel extends Model {
    protected const POST_TYPE = 'event';

    protected static function get_post_type_args(): array {
        return [
            'labels' => [
                'name' => 'Events',
                'singular_name' => 'Event',
                'add_new' => 'Add New Event',
                'edit_item' => 'Edit Event',
                'view_item' => 'View Event'
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest' => true
        ];
    }

    public function __construct(Config $config) {
        parent::__construct($config);
        $this->setup_metaboxes();
        $this->cache_duration = 7200; // 2 hours
    }

    private function setup_metaboxes(): void {
        // Event Details MetaBox
        $event_details = MetaBox::create('event_details', 'Event Details', static::POST_TYPE, $this->config)
            ->set_caching(true, 3600)
            ->add_field('start_date', 'Start Date', 'date', [], [
                'required' => true
            ], [
                'allow_quick_edit' => true,
                'description' => 'Event start date',
                'no_past_dates' => true
            ])
            ->add_field('end_date', 'End Date', 'date', [], [
                'required' => true
            ], [
                'description' => 'Event end date',
                'no_past_dates' => true
            ])
            ->add_field('venue_name', 'Venue', 'text', [], [
                'required' => true,
                'class' => 'widefat'
            ], [
                'allow_quick_edit' => true,
                'description' => 'Event venue name'
            ])
            ->add_field('max_attendees', 'Max Attendees', 'number', [], [
                'min' => 1,
                'max' => 10000
            ], [
                'allow_quick_edit' => true,
                'description' => 'Maximum number of attendees'
            ])
            ->add_field('ticket_price', 'Ticket Price', 'number', [], [
                'min' => 0,
                'step' => 0.01
            ], [
                'sanitize_callback' => function($value) {
                    return round(floatval($value), 2);
                }
            ])
            ->add_field('event_image', 'Event Image', 'wp_media', [], [
                'multiple' => false
            ], [
                'description' => 'Featured image for the event'
            ])
            ->onPreSave(function($post_id, $metabox) {
                Debugger::info('event-manager-pro', "About to save event {$post_id}");
            })
            ->onSuccess(function($post_id, $metabox) {
                // Clear cache
                Cache::delete("event_details_{$post_id}", 'events');
                
                // Send notification
                $notification = Registry::get('event-manager-pro', 'notification');
                $notification->success('Event details saved successfully!');
                
                Debugger::info('event-manager-pro', "Event {$post_id} saved successfully");
            })
            ->onError(function($errors, $post_id, $metabox) {
                $notification = Registry::get('event-manager-pro', 'notification');
                $notification->error('Please fix the validation errors and try again.');
                
                Debugger::error('event-manager-pro', "Validation errors for event {$post_id}", $errors);
            })
            ->setup_actions();

        // Event Status MetaBox
        $event_status = MetaBox::create('event_status', 'Event Status', static::POST_TYPE, $this->config)
            ->set('context', 'side')
            ->add_field('status', 'Status', 'select', [
                'draft' => 'Draft',
                'published' => 'Published',
                'cancelled' => 'Cancelled',
                'postponed' => 'Postponed'
            ], ['required' => true], [
                'default' => 'draft',
                'allow_quick_edit' => true
            ])
            ->add_field('featured', 'Featured Event', 'checkbox', [], [], [
                'description' => 'Mark as featured event'
            ])
            ->setup_actions();

        $this->register_metaboxes([$event_details, $event_status]);
    }

    public function get_upcoming_events(int $limit = 10): array {
        return $this->get_posts([
            'meta_query' => [
                [
                    'key' => 'event_details_event_start_date',
                    'value' => date('Y-m-d'),
                    'compare' => '>='
                ]
            ],
            'posts_per_page' => $limit,
            'orderby' => 'meta_value',
            'meta_key' => 'event_details_event_start_date',
            'order' => 'ASC'
        ], true);
    }

    public function get_featured_events(): array {
        return $this->get_posts([
            'meta_query' => [
                [
                    'key' => 'event_status_event_featured',
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ], true);
    }
}

// Initialize the plugin
new EventManagerPro();
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
    'notification' => Notification::create($config),
    'metabox' => MetaBox::create('product_details', 'Product Details', 'product', $config)
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

// Use MetaBox callbacks for error handling
$metabox->onError(function($errors, $post_id, $metabox) {
    error_log("Validation failed for post {$post_id}: " . print_r($errors, true));
    
    $notification = Registry::get('my-plugin', 'notification');
    $notification->error('Please fix the errors and try again.');
});
```

### 4. Development vs Production

```php
// Use config-based environment detection
if ($config->isDevelopment()) {
    Debugger::initFromConfig($config);
    ViewLoader::disable_cache();
    
    // Development-specific MetaBox setup
    $metabox->onPreSave(function($post_id) {
        Debugger::info('my-plugin', "Saving post {$post_id}");
    });
} else {
    // Production optimizations
    ViewLoader::enable_cache(3600);
    Cache::set_many($production_cache_data);
}
```

## Migration from EasyMetabox

If you're migrating from EasyMetabox to WPToolkit, here's what's changed:

### Namespace Changes
```php
// Old EasyMetabox
use Codad5\EasyMetabox\MetaBox;
use Codad5\EasyMetabox\helpers\InputValidator;

// New WPToolkit
use Codad5\WPToolkit\DB\MetaBox;
use Codad5\WPToolkit\Utils\InputValidator;
```

### Enhanced Features
```php
// Old way - basic error handling
$metabox = new MetaBox('product', 'Product Details', 'product');

// New way - with callbacks and config
$metabox = MetaBox::create('product', 'Product Details', 'product', $config)
    ->onError(function($errors) {
        // Custom error handling
    })
    ->onSuccess(function($post_id) {
        // Custom success handling
    })
    ->set_caching(true, 3600);
```

### Improved Validation
```php
// Old validation
$result = InputValidator::validate('email', $value, $field); // Returns bool

// New validation
$result = InputValidator::validate('email', $value, $field); // Returns bool|string
if ($result !== true) {
    echo $result; // Detailed error message
}

// Custom validators
InputValidator::register_validator('custom_type', function($value, $field) {
    return $value === 'expected' ? true : 'Custom error message';
});
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

**WPToolkit** - Modern WordPress development with dependency injection, service registry, advanced form management, and enterprise-grade architecture! 🚀