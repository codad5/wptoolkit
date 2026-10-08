# WPToolkit

**Modern WordPress development made simple** - A comprehensive toolkit that transforms WordPress plugin and theme development with dependency injection, service registry architecture, and advanced form management.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.0-blue.svg)](https://wordpress.org)

## ✨ Why WPToolkit?

- **🏗️ Modern Architecture** - Service registry with dependency injection for scalable applications
- **📋 Advanced Forms** - MetaBox system with validation, lifecycle hooks, and caching
- **🚀 Zero Boilerplate** - Factory methods and fluent APIs reduce repetitive code
- **🔄 Multi-App Ready** - Manage multiple plugins/themes from a single codebase
- **⚡ Performance First** - Built-in caching, optimization, and smart loading
- **🛡️ Type Safe** - Full PHP 8.1+ support with strict typing

## 📦 Installation

### Via Composer (Recommended)
```bash
composer require codad5/wptoolkit
```

### Direct Download
1. Download the `src` directory
2. Place in your plugin: `./lib/wptoolkit/`
3. Include: `require_once __DIR__ . '/lib/wptoolkit/autoloader.php';`

## 🚀 Quick Start

```php
<?php
use Codad5\WPToolkit\Registry;
use Codad5\WPToolkit\Utils\{Config, Settings, Page, Notification};
use Codad5\WPToolkit\DB\{Model, MetaBox};

// 1. Create configuration
$config = Config::plugin('my-plugin', __FILE__, [
    'name' => 'My Awesome Plugin',
    'version' => '1.0.0'
]);

// 2. Register with service registry
Registry::registerApp($config);

// 3. Create services with fluent API
$settings = Settings::create([
    'api_key' => [
        'type' => 'text',
        'label' => 'API Key',
        'required' => true
    ]
], $config);

$metabox = MetaBox::create('product_details', 'Product Details', 'product', $config)
    ->add_field('price', 'Price', 'number', [], ['required' => true])
    ->onSuccess(function($post_id) {
        // Handle successful save
    })
    ->setup_actions();

// 4. Register all services
Registry::addMany($config, [
    'settings' => $settings,
    'page' => Page::create($config),
    'notification' => Notification::create($config),
    'metabox' => $metabox
]);

// 5. Use anywhere in your app
$settings = Registry::get('my-plugin', 'settings');
$settings->set('api_key', 'your-key');
```

## 🔧 Core Components

### Service Registry
Centralized dependency management with lazy loading and service factories.

```php
// Register services
Registry::registerApp($config, [
    'settings' => Settings::create([], $config),
    'api' => ApiClient::create($config)
]);

// Lazy loading with factories
Registry::factory($config, 'expensive_service', function($config) {
    return new ExpensiveService($config->get('api_key'));
});

// Access from anywhere
$api = Registry::get('my-plugin', 'api');
```

### Advanced MetaBox System
Type-safe forms with validation, callbacks, and caching.

```php
$metabox = MetaBox::create('event_details', 'Event Details', 'event', $config)
    ->add_field('start_date', 'Start Date', 'date', [], ['required' => true])
    ->add_field('venue', 'Venue', 'select', $venue_options, ['required' => true])
    ->set_caching(true, 3600)
    ->onSuccess(function($post_id, $metabox) {
        wp_cache_delete("event_{$post_id}");
    })
    ->setup_actions();
```

### Database Models
Abstract models with MetaBox integration and CRUD operations.

```php
class EventModel extends Model {
    protected const POST_TYPE = 'event';
    
    public function get_upcoming_events(): array {
        return $this->get_posts([
            'meta_query' => [
                ['key' => 'start_date', 'value' => date('Y-m-d'), 'compare' => '>=']
            ]
        ], true);
    }
}
```

### Smart Caching
Multi-level caching with group management and automatic invalidation.

```php
// Simple caching
Cache::set('user_data', $data, 3600, 'users');
$data = Cache::get('user_data', [], 'users');

// Remember pattern
$expensive_data = Cache::remember('complex_query', function() {
    return perform_database_query();
}, 1800, 'database');

// Group management
Cache::clear_group('users');
```

### REST API Management
Multi-version API with deprecation support and middleware.

```php
$api = RestRoute::create($config, ['v1', 'v2'], 'v1')
    ->get('v1', '/users', function($request) {
        return ['users' => get_users()];
    })
    ->addMiddleware('v1', $auth_middleware)
    ->deprecateVersion('v1', '2024-01-01', '2025-01-01', 'v2');
```

### Template System
Flexible view loading with inheritance and caching.

```php
// Setup paths
ViewLoader::add_path(get_template_directory() . '/templates', 5);
ViewLoader::enable_cache(3600);

// Load templates
ViewLoader::load('admin/dashboard', ['stats' => $data]);

// Template inheritance
ViewLoader::layout('layouts/admin', 'admin/settings', $data);
```

## 📋 Complete Example

Here's a full plugin example showcasing WPToolkit's capabilities:

<details>
<summary>View Complete Event Manager Plugin</summary>

```php
<?php
/**
 * Plugin Name: Event Manager Pro
 * Description: Advanced event management with WPToolkit
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use Codad5\WPToolkit\Utils\{Config, Registry, Settings, Page, Notification};
use Codad5\WPToolkit\DB\{Model, MetaBox};

class EventManagerPro {
    private Config $config;

    public function __construct() {
        // Initialize configuration
        $this->config = Config::plugin('event-manager-pro', __FILE__, [
            'name' => 'Event Manager Pro',
            'version' => '1.0.0'
        ]);

        Registry::registerApp($this->config);
        $this->register_services();
        $this->setup_hooks();
    }

    private function register_services(): void {
        // Settings
        $settings = Settings::create([
            'default_venue' => [
                'type' => 'text',
                'label' => 'Default Venue',
                'group' => 'defaults'
            ],
            'max_attendees' => [
                'type' => 'number',
                'label' => 'Max Attendees',
                'default' => 1000,
                'group' => 'limits'
            ]
        ], $this->config);

        // Event model
        $event_model = EventModel::get_instance($this->config);

        Registry::addMany($this->config, [
            'settings' => $settings,
            'page' => Page::create($this->config),
            'notification' => Notification::create($this->config),
            'event_model' => $event_model
        ]);
    }

    private function setup_hooks(): void {
        add_action('init', function() {
            $event_model = Registry::get('event-manager-pro', 'event_model');
            $event_model->run();
        });
    }
}

class EventModel extends Model {
    protected const POST_TYPE = 'event';
    protected const META_PREFIX = 'event_';

    protected static function get_post_type_args(): array {
        return [
            'labels' => [
                'name' => 'Events',
                'singular_name' => 'Event'
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail']
        ];
    }

    public function __construct(Config $config) {
        parent::__construct($config);
        $this->setup_metaboxes();
    }

    private function setup_metaboxes(): void {
        $metabox = MetaBox::create('event_details', 'Event Details', self::POST_TYPE, $this->config)
            ->add_field('start_date', 'Start Date', 'date', [], ['required' => true])
            ->add_field('venue', 'Venue', 'text', [], ['required' => true])
            ->add_field('max_attendees', 'Max Attendees', 'number', [], ['min' => 1])
            ->onSuccess(function($post_id) {
                wp_cache_delete("event_{$post_id}");
            })
            ->setup_actions();

        $this->register_metabox($metabox);
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
            'order' => 'ASC'
        ], true);
    }
}

// Initialize
new EventManagerPro();
```

</details>

## 📚 Documentation

- **[Examples & Tutorials](EXAMPLES.md)** - Detailed examples for each component
- **[API Reference](docs/api.md)** - Complete API documentation
- **[Migration Guide](docs/migration.md)** - Upgrade from older versions
- **[Best Practices](docs/best-practices.md)** - Recommended patterns and approaches

## 🔄 Migration from EasyMetabox

Upgrading from EasyMetabox? WPToolkit is fully backward compatible with enhanced features:

```php
// Old EasyMetabox
$metabox = new MetaBox('product', 'Product Details', 'product');

// New WPToolkit - same interface, more features
$metabox = MetaBox::create('product', 'Product Details', 'product', $config)
    ->onError(function($errors) { /* Handle errors */ })
    ->set_caching(true, 3600);
```

## 🛠️ Requirements

- **PHP 8.1+** (Modern PHP features like readonly properties, union types)
- **WordPress 5.0+**
- **Composer** (recommended for dependency management)

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support & Community

- **[GitHub Issues](https://github.com/codad5/wptoolkit/issues)** - Bug reports and feature requests
- **[Discussions](https://github.com/codad5/wptoolkit/discussions)** - Community support and ideas
- **[Wiki](https://github.com/codad5/wptoolkit/wiki)** - Additional documentation and guides

---

**WPToolkit** - Transforming WordPress development with modern architecture and developer experience! 🚀