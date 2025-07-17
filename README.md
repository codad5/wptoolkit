# WPToolkit

**Your go-to WordPress development library** - A comprehensive collection of utility classes that streamline WordPress plugin and theme development.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.0-blue.svg)](https://wordpress.org)

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

use Codad5\WPToolkit\Utils\Config;
use Codad5\WPToolkit\Utils\Settings;
// ... other classes
```

### Method 2: Direct Download

1. Download the `src` directory from this repository
2. Place it in your plugin directory (e.g., `./lib/wptoolkit/`)
3. Include the autoloader:

```php
<?php
require_once __DIR__ . '/lib/wptoolkit/autoloader.php';

use Codad5\WPToolkit\Utils\Config;
use Codad5\WPToolkit\Utils\Settings;
// ... other classes
```

> **Note:** Avoid using both installation methods simultaneously to prevent conflicts.

## Quick Start

```php
<?php
use Codad5\WPToolkit\Utils\Config;
use Codad5\WPToolkit\Utils\Settings;
use Codad5\WPToolkit\Utils\Page;
use Codad5\WPToolkit\Utils\Notification;

// Initialize your plugin configuration
Config::init([
    'name' => 'My Awesome Plugin',
    'slug' => 'my-awesome-plugin',
    'version' => '1.0.0'
]);

// Initialize components
Settings::init();
Page::init();
Notification::init();
```

## Available Classes

### Core Utilities

#### Config
Immutable configuration management for your plugin.

```php
// Initialize configuration
Config::init([
    'name' => 'My Plugin',
    'slug' => 'my-plugin',
    'version' => '1.0.0',
    'file' => __FILE__
]);

// Get configuration values
$plugin_name = Config::get('name');
$plugin_slug = Config::get('slug');
```

#### Requirements
Check plugin requirements before activation.

```php
$requirements = new Requirements();
$is_compatible = $requirements
    ->php('8.0')
    ->wp('5.0')
    ->plugins(['woocommerce/woocommerce.php'])
    ->theme('twentytwentythree')
    ->met();

if (!$is_compatible) {
    // Handle incompatibility
}
```

### Settings Management

#### Settings
Comprehensive WordPress settings API wrapper with validation and sanitization.

```php
// Initialize settings
Settings::init([
    'api_key' => [
        'type' => 'text',
        'label' => 'API Key',
        'description' => 'Enter your API key',
        'required' => true,
        'sanitize_callback' => 'sanitize_text_field'
    ],
    'enable_features' => [
        'type' => 'checkbox',
        'label' => 'Enable Advanced Features',
        'default' => false
    ],
    'theme_color' => [
        'type' => 'select',
        'label' => 'Theme Color',
        'choices' => [
            'blue' => 'Blue',
            'red' => 'Red',
            'green' => 'Green'
        ],
        'default' => 'blue'
    ]
]);

// Get/set settings
$api_key = Settings::get('api_key');
Settings::set('enable_features', true);

// Render form field
echo Settings::render_field('api_key');
```

### Page Management

#### Page
Create admin and frontend pages with ease.

```php
// Initialize page system
Page::init();

// Add admin menu page
Page::add_menu_page('my-plugin-dashboard', [
    'page_title' => 'My Plugin Dashboard',
    'menu_title' => 'My Plugin',
    'capability' => 'manage_options',
    'icon' => 'dashicons-admin-tools',
    'callback' => function() {
        echo '<h1>Welcome to My Plugin</h1>';
    }
]);

// Add submenu page
Page::add_submenu_page('my-plugin-settings', [
    'parent_slug' => 'my-plugin-dashboard',
    'page_title' => 'Plugin Settings',
    'menu_title' => 'Settings',
    'callback' => 'render_settings_page'
]);

// Add frontend page
Page::add_frontend_page('my-custom-page', [
    'title' => 'Custom Page',
    'template' => 'custom-page.php',
    'public' => true
]);

// Get page URLs
$admin_url = Page::get_admin_url('my-plugin-dashboard');
$frontend_url = Page::get_frontend_url('my-custom-page');
```

### Notifications

#### Notification
Display admin notices with automatic dismissal and targeting.

```php
// Initialize notifications
Notification::init();

// Add notifications
Notification::success('Settings saved successfully!');
Notification::error('Something went wrong.', 'current', 300); // 5 minutes
Notification::warning('Please update your API key.', 'plugin');
Notification::info('New features available!', 'all');

// Programmatic notifications
if ($operation_success) {
    Notification::success('Operation completed successfully!');
} else {
    Notification::error('Operation failed. Please try again.');
}
```

### REST API

#### RestRoute
Multi-version REST API management with deprecation support.

```php
// Initialize REST API
RestRoute::init('my-plugin', ['v1', 'v2'], 'v1');

// Add routes
RestRoute::get('v1', '/users', 'get_users_callback');
RestRoute::post('v1', '/users', 'create_user_callback');
RestRoute::put('v1', '/users/(?P<id>\d+)', 'update_user_callback');
RestRoute::delete('v1', '/users/(?P<id>\d+)', 'delete_user_callback');

// Add route with validation
RestRoute::add_route('v1', '/products', [
    'methods' => 'POST',
    'callback' => 'create_product',
    'args' => [
        'name' => [
            'required' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ],
        'price' => [
            'required' => true,
            'type' => 'number',
            'validate_callback' => function($value) {
                return $value > 0;
            }
        ]
    ]
]);

// Copy routes between versions
RestRoute::copy_routes('v1', 'v2', ['/deprecated-endpoint']);

// Deprecate version
RestRoute::deprecate_version('v1', '2024-01-01', '2025-01-01', 'v2');

// Get route URL
$api_url = RestRoute::get_route_url('v1', '/users');
```

### File Operations

#### Filesystem
WordPress filesystem wrapper with media library integration.

```php
// Initialize filesystem
Filesystem::init(['svg', 'webp']); // Add allowed file types

// File operations
$content = Filesystem::get_contents('/path/to/file.txt');
Filesystem::put_contents('/path/to/new-file.txt', 'Hello World!');
Filesystem::copy_file('/source.txt', '/destination.txt');
Filesystem::move_file('/old-location.txt', '/new-location.txt');

// Directory operations
Filesystem::create_directory('/new-directory');
Filesystem::delete_directory('/old-directory', true); // recursive

// Media library integration
$attachment_id = Filesystem::upload_to_media_library($_FILES['upload'], 'My File');
$file_info = Filesystem::get_media_file_info($attachment_id);

// Plugin-specific uploads
$upload_dir = Filesystem::create_plugin_upload_dir('documents');
$unique_filename = Filesystem::get_unique_filename('document.pdf', 'documents');

// File information
$file_size = Filesystem::get_file_size('/path/to/file.txt');
$formatted_size = Filesystem::format_file_size($file_size); // "1.5 MB"
$mime_type = Filesystem::get_mime_type('/path/to/image.jpg');
```

### Development Tools

#### Debugger
Comprehensive debugging tools for development.

```php
// Initialize debugger
Debugger::init(WP_DEBUG);

// Console logging (browser console)
Debugger::console('Debug message');
Debugger::info('Information message');
Debugger::warn('Warning message');
Debugger::error('Error message');

// Variable inspection
$data = ['key' => 'value', 'number' => 42];
Debugger::print_r($data);
Debugger::var_dump($data);

// Breakpoints
Debugger::breakpoint('Checking user data', ['user_id' => $user_id]);

// Performance timing
$start = Debugger::timer('Database Query');
// ... database operation
Debugger::timer('Database Query', $start); // Logs duration

// WordPress-specific debugging
Debugger::query_info(); // Current WP_Query information
Debugger::notification('Debug info displayed'); // Admin notice
```

#### Autoloader
PSR-4 compatible autoloader with WordPress optimizations.

```php
// Initialize autoloader
Autoloader::init([
    'MyPlugin\\' => __DIR__ . '/src/',
    'MyPlugin\\Vendor\\' => __DIR__ . '/vendor-lib/'
]);

// Add namespace
Autoloader::add_namespace('MyPlugin\\Modules\\', __DIR__ . '/modules/');

// Add class map
Autoloader::add_class_map('MyPlugin\\SpecialClass', __DIR__ . '/special/SpecialClass.php');

// Generate class map from directory
$class_map = Autoloader::generate_class_map(__DIR__ . '/lib/', 'MyPlugin\\Lib\\');
Autoloader::add_class_maps($class_map);

// Check if class can be loaded
if (Autoloader::can_load_class('MyPlugin\\SomeClass')) {
    $instance = new MyPlugin\SomeClass();
}
```

### API Integration

#### APIHelper
Abstract base class for creating API integrations with caching and error handling.

```php
class MyAPIHelper extends APIHelper {
    protected static string $name = 'MyAPI';
    const HOST = 'https://api.example.com';
    
    protected static function get_endpoints(): array {
        return [
            'get_user' => [
                'route' => '/users/{{id}}',
                'method' => 'GET',
                'cache' => 60 // Cache for 60 minutes
            ],
            'create_user' => [
                'route' => '/users',
                'method' => 'POST',
                'cache' => false
            ],
            'list_posts' => [
                'route' => '/posts',
                'method' => 'GET',
                'params' => [
                    'per_page' => 10,
                    'status' => 'published'
                ],
                'cache' => function($data) {
                    // Cache only if we have data
                    return !empty($data);
                }
            ]
        ];
    }
}

// Usage
try {
    // Get user with ID substitution
    $user = MyAPIHelper::make_request('get_user', [], ['id' => '123']);
    
    // Create new user
    $new_user = MyAPIHelper::make_request('create_user', [
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);
    
    // List posts with custom parameters
    $posts = MyAPIHelper::make_request('list_posts', ['per_page' => 20]);
    
} catch (Exception $e) {
    Notification::error('API Error: ' . $e->getMessage());
}

// Cache management
$call_count = MyAPIHelper::get_api_call_count();
$cached_data = MyAPIHelper::list_cache();
MyAPIHelper::clear_cache();
```

## Complete Plugin Example

```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Description: A WordPress plugin built with WPToolkit
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include WPToolkit
require_once __DIR__ . '/vendor/autoload.php';

use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification, RestRoute, Debugger};

class MyAwesomePlugin {
    
    public function __construct() {
        $this->init_config();
        $this->init_components();
        $this->setup_hooks();
    }
    
    private function init_config(): void {
        Config::init([
            'name' => 'My Awesome Plugin',
            'slug' => 'my-awesome-plugin',
            'version' => '1.0.0',
            'file' => __FILE__
        ]);
    }
    
    private function init_components(): void {
        // Initialize settings
        Settings::init([
            'api_key' => [
                'type' => 'text',
                'label' => 'API Key',
                'group' => 'api'
            ],
            'cache_duration' => [
                'type' => 'number',
                'label' => 'Cache Duration (minutes)',
                'default' => 60,
                'group' => 'performance'
            ]
        ]);
        
        // Initialize pages
        Page::init();
        Page::add_menu_page('my-awesome-plugin', [
            'page_title' => 'My Awesome Plugin',
            'menu_title' => 'Awesome Plugin',
            'callback' => [$this, 'render_dashboard']
        ]);
        
        // Initialize notifications
        Notification::init();
        
        // Initialize REST API
        RestRoute::init('my-awesome-plugin');
        RestRoute::get('v1', '/status', [$this, 'get_status']);
        
        // Initialize debugger in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            Debugger::init(true);
        }
    }
    
    private function setup_hooks(): void {
        add_action('admin_init', [$this, 'admin_init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    public function admin_init(): void {
        // Check if settings are configured
        $api_key = Settings::get('api_key');
        if (empty($api_key) && Page::is_plugin_admin_page()) {
            Notification::warning('Please configure your API key in the settings.');
        }
    }
    
    public function render_dashboard(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(Config::get('name')); ?></h1>
            <p>Welcome to your awesome plugin dashboard!</p>
            
            <div class="card">
                <h2>Settings</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('api'); ?>
                    <?php echo Settings::render_field('api_key'); ?>
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    public function get_status(): array {
        return [
            'plugin' => Config::get('name'),
            'version' => Config::get('version'),
            'status' => 'active'
        ];
    }
    
    public function enqueue_scripts(): void {
        wp_enqueue_style(
            Config::get('slug') . '-style',
            plugins_url('assets/style.css', Config::get('file')),
            [],
            Config::get('version')
        );
    }
}

// Initialize the plugin
new MyAwesomePlugin();
```

## Best Practices

### Configuration
Always initialize the Config class first with your plugin details:

```php
Config::init([
    'name' => 'Your Plugin Name',
    'slug' => 'your-plugin-slug',
    'version' => '1.0.0',
    'file' => __FILE__ // Important for asset URLs
]);
```

### Error Handling
Use try-catch blocks with API calls and provide user feedback:

```php
try {
    $result = MyAPIHelper::make_request('get_data');
    Notification::success('Data retrieved successfully!');
} catch (Exception $e) {
    Notification::error('Failed to retrieve data: ' . $e->getMessage());
    Debugger::error('API Error', ['exception' => $e]);
}
```

### Settings Organization
Group related settings together:

```php
Settings::init([
    'api_key' => ['group' => 'api'],
    'api_timeout' => ['group' => 'api'],
    'cache_enabled' => ['group' => 'performance'],
    'cache_duration' => ['group' => 'performance']
]);
```

### Development vs Production
Use conditional debugging:

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    Debugger::init(true);
    Debugger::console('Plugin loaded in debug mode');
}
```

## Requirements

- PHP 8.0 or higher
- WordPress 5.0 or higher
- Modern browser support for console debugging features

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

If you encounter any issues or have questions, please [open an issue](https://github.com/codad5/wptoolkit/issues) on GitHub.

---

**WPToolkit** - Making WordPress development more enjoyable, one utility at a time! 🚀