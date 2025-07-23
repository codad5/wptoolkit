# WPToolkit

**The Modern WordPress Development Framework** - Transform your WordPress development with enterprise-grade architecture, dependency injection, and advanced form management. Built for developers who demand scalability, maintainability, and performance.

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.1-blue.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D5.0-blue.svg)](https://wordpress.org)

## 🌟 Why WPToolkit?

WPToolkit isn't just another WordPress library—it's a complete paradigm shift that brings modern software architecture to WordPress development. Whether you're building plugins, themes, or complex applications, WPToolkit provides the foundation for scalable, maintainable code.

### 🏗️ **Enterprise Architecture**

- **Service Registry Pattern** - Centralized dependency management with lazy loading
- **Dependency Injection** - Testable, modular components with clear separation of concerns
- **Singleton Models** - Memory-efficient database models with automatic lifecycle management
- **Multi-App Support** - Manage multiple plugins/themes from unified codebase

### 📋 **Advanced Form System**

- **MetaBox Framework** - Type-safe custom fields with validation and lifecycle hooks
- **Input Validation** - Extensible validation system with custom validators
- **Admin Integration** - Custom admin columns, quick edit, and sorting capabilities
- **Caching Layer** - Built-in field-level caching for performance optimization

### ⚡ **Performance First**

- **Smart Caching** - Multi-level caching with group management and auto-invalidation
- **Lazy Loading** - Components load only when needed, reducing memory footprint
- **Database Optimization** - Model layer with query caching and batch operations
- **Template Caching** - View system with inheritance and intelligent caching

### 🔧 **Developer Experience**

- **Fluent APIs** - Chainable methods for intuitive code writing
- **Type Safety** - Full PHP 8.1+ support with strict typing and modern features
- **Error Handling** - Comprehensive validation and error reporting
- **Hot Reloading** - Models can be paused/resumed without losing state

## 📦 Core Modules

### 🔌 **Service Registry**

The heart of WPToolkit's architecture. Manages all your application services with dependency injection, lazy loading, and cross-app communication.

**Key Features:**

- Centralized service management
- Factory methods for expensive resources
- Service aliases and cross-app access
- Automatic service discovery

### 🗄️ **Database Models**

Singleton-based models that provide CRUD operations, MetaBox integration, and admin interface customization.

**Key Features:**

- Automatic post type registration
- Custom admin columns with sorting
- MetaBox integration and field management
- Caching and performance optimization
- Lifecycle management (run/pause/resume)

### 📝 **MetaBox System**

Advanced form builder with validation, callbacks, and WordPress integration.

**Key Features:**

- Type-safe field definitions
- Lifecycle hooks (onSave, onError, onSuccess)
- Quick edit support
- Field-level caching
- Custom sanitization and validation

### 🚀 **Caching Engine**

Multi-level caching system with group management and bulk operations.

**Key Features:**

- Transient-based storage
- Group organization
- Bulk set/get operations
- Cache statistics and management
- Remember pattern support

### 🌐 **REST API Management**

Multi-version API system with deprecation support and middleware.

**Key Features:**

- Version management and deprecation
- Middleware support (global and version-specific)
- Route copying for backward compatibility
- Standardized response formats
- Permission and nonce verification

### 🎨 **Template System**

Flexible view loader with template inheritance and caching.

**Key Features:**

- Multiple template paths with priority
- Template inheritance with sections
- Global data injection
- Intelligent caching
- Error handling and debugging

### ✅ **Input Validation**

Extensible validation system with custom validators and error handling.

**Key Features:**

- Built-in validators for common types
- Custom validator registration
- Global validation rules
- Bulk validation support
- Localized error messages

### ⚙️ **Settings Management**

WordPress Settings API integration with validation and grouping.

**Key Features:**

- Grouped settings organization
- Import/export functionality
- Field rendering helpers
- Cache integration
- Notification integration

### 📄 **Page Management**

Admin and frontend page creation with template integration.

**Key Features:**

- Admin menu and submenu pages
- Frontend page routing
- Dashboard widgets
- Template directory management
- URL generation helpers

### 📢 **Notification System**

Advanced notification management with targeting and persistence.

**Key Features:**

- Multiple notification types
- Page-specific targeting
- Expiration and dismissal
- Global static methods
- Custom callback support

### 🔄 **Autoloader**

PSR-4 compatible autoloading with namespace management and plugin tracking.

**Key Features:**

- Multiple namespace support
- Class mapping for direct loading
- Plugin-specific tracking
- Cache management
- Automatic class discovery

### 🌐 **API Helper**

Abstract base class for HTTP requests and external API integration.

**Key Features:**

- Request caching and management
- Error handling and retry logic
- Response formatting
- Rate limiting support
- Request/response logging

### 📁 **Filesystem**

Comprehensive file operations and WordPress media integration.

**Key Features:**

- WordPress filesystem integration
- Media library management
- File type validation
- Upload directory management
- File metadata and permissions

### 🔍 **Requirements Checker**

Plugin/theme requirements validation for PHP, WordPress, and dependencies.

**Key Features:**

- PHP version checking
- WordPress version validation
- Plugin dependency verification
- Theme compatibility
- Composer package validation

## 🚀 Quick Start

```php
<?php
// 1. Initialize your application
$config = Config::plugin('my-plugin', __FILE__, [
    'name' => 'My Advanced Plugin',
    'version' => '2.0.0'
]);

// 2. Register with service registry
Registry::registerApp($config);

// 3. Create your model with MetaBoxes
class ProductModel extends Model {
    protected const POST_TYPE = 'product';

    public function __construct(Config $config) {
        parent::__construct($config);
        $this->setup_metaboxes();
    }

    private function setup_metaboxes(): void {
        $metabox = MetaBox::create('product_details', 'Product Details', self::POST_TYPE, $this->config)
            ->add_field('price', 'Price', 'number', [], ['required' => true])
            ->onSuccess(function($post_id) {
                Cache::delete("product_{$post_id}");
            })
            ->setup_actions();

        $this->register_metabox($metabox);
    }
}

// 4. Register and run your model
$product_model = ProductModel::get_instance($config);
$product_model->run();

// 5. Services available anywhere
Registry::addMany($config, [
    'settings' => Settings::create($config),
    'api' => RestRoute::create($config),
    'filesystem' => Filesystem::create($config)
]);
```

## 📚 Documentation

- **[API Reference](API.md)** - Complete method documentation for all classes
- **[Examples & Tutorials](EXAMPLES.md)** - Detailed examples and usage patterns
- **[Migration Guide](docs/migration.md)** - Upgrade from older versions
- **[Best Practices](docs/best-practices.md)** - Recommended patterns and architecture

## 🔄 Upgrading from EasyMetabox

WPToolkit is the evolution of EasyMetabox with full backward compatibility plus enterprise features:

- **Same API** - Your existing MetaBox code works unchanged
- **Enhanced Features** - Lifecycle hooks, caching, admin columns
- **Better Architecture** - Service registry, dependency injection
- **Performance** - Singleton models, smart caching, lazy loading

## 🛠️ System Requirements

- **PHP 8.1+** - Modern PHP features (readonly properties, union types, enums)
- **WordPress 5.0+** - Gutenberg and REST API support
- **Memory** - 128MB minimum (256MB recommended for complex applications)
- **Server** - Apache/Nginx with mod_rewrite support

## 🚀 Installation

### Via Composer (Recommended)

```bash
composer require codad5/wptoolkit
```

### Direct Download

1. Download the latest release
2. Extract to `./lib/wptoolkit/` in your plugin/theme
3. Include: `require_once __DIR__ . '/lib/wptoolkit/autoloader.php';`

### Autoloader Setup

```php
<?php
use Codad5\WPToolkit\Utils\Autoloader;

Autoloader::init([
    'MyPlugin\\' => __DIR__ . '/src/',
    'MyPlugin\\Models\\' => __DIR__ . '/models/'
], [], false, plugin_basename(__FILE__));
```

## 🏢 Production Ready

WPToolkit powers enterprise WordPress applications with:

- **Scalability** - Handle thousands of posts with optimized queries
- **Reliability** - Comprehensive error handling and fallbacks
- **Performance** - Multi-level caching and lazy loading
- **Security** - Input validation and sanitization
- **Maintainability** - Clean architecture and separation of concerns

## 🤝 Contributing

We welcome contributions from the WordPress community:

1. **Fork** the repository
2. **Create** your feature branch (`git checkout -b feature/amazing-feature`)
3. **Test** your changes thoroughly
4. **Commit** with clear messages (`git commit -m 'Add amazing feature'`)
5. **Push** to your branch (`git push origin feature/amazing-feature`)
6. **Open** a Pull Request

## 📄 License

Licensed under the MIT License - see [LICENSE](LICENSE) for details.

## 🆘 Support & Community

- **[GitHub Issues](https://github.com/codad5/wptoolkit/issues)** - Bug reports and feature requests
- **[Discussions](https://github.com/codad5/wptoolkit/discussions)** - Community support
- **[Wiki](https://github.com/codad5/wptoolkit/wiki)** - Extended documentation
- **[Changelog](CHANGELOG.md)** - Version history and updates

---

**Transform your WordPress development today with WPToolkit - where modern architecture meets WordPress simplicity!** 🚀

_Built with ❤️ by the WordPress development community_
