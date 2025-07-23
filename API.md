# WPToolkit API Reference

Complete method documentation for all WPToolkit classes and utilities.

## Table of Contents

1. [Config](#config) - Application configuration management
2. [Registry](#registry) - Service registry and dependency injection
3. [Model](#model) - Database models with admin integration
4. [MetaBox](#metabox) - Advanced form management
5. [Cache](#cache) - Multi-level caching system
6. [RestRoute](#restroute) - REST API management
7. [ViewLoader](#viewloader) - Template system
8. [InputValidator](#inputvalidator) - Input validation
9. [Settings](#settings) - Settings management
10. [Page](#page) - Page management
11. [Notification](#notification) - Notification system
12. [Autoloader](#autoloader) - Class autoloading
13. [APIHelper](#apihelper) - HTTP requests and API integration
14. [Filesystem](#filesystem) - File operations
15. [Requirements](#requirements) - Requirements validation

---

## Config

**Purpose**: Immutable configuration management for WordPress plugins and themes with environment detection and path/URL helpers.

### Static Factory Methods

#### `Config::plugin(string $slug, string $file, array $config = []): Config`
Create configuration for a WordPress plugin.
```php
$config = Config::plugin('my-plugin', __FILE__, ['version' => '1.0.0']);
```

#### `Config::theme(string $slug, array $config = []): Config`
Create configuration for a WordPress theme.
```php
$config = Config::theme('my-theme', ['supports' => ['post-thumbnails']]);
```

#### `Config::create(array $config): Config`
Create generic application configuration.
```php
$config = Config::create(['slug' => 'my-app', 'name' => 'My App']);
```

### Instance Methods

#### `get(string $key, mixed $default = null): mixed`
Get configuration value.
```php
$version = $config->get('version', '1.0.0');
```

#### `with(array $config): Config`
Create new instance with additional/updated configuration.
```php
$new_config = $config->with(['debug' => true]);
```

#### `only(array $keys): Config`
Create new instance with only specified keys.
```php
$subset = $config->only(['slug', 'version']);
```

#### `path(string $path = ''): string`
Get absolute path within plugin/theme directory.
```php
$template_path = $config->path('templates/admin.php');
```

#### `url(string $path = ''): string`
Get URL within plugin/theme directory.
```php
$asset_url = $config->url('assets/style.css');
```

#### `isDevelopment(): bool`
Check if in development mode.
```php
if ($config->isDevelopment()) { /* debug code */ }
```

---

## Registry

**Purpose**: Centralized service management with dependency injection, lazy loading, and cross-application communication.

### Static Methods

#### `Registry::registerApp(Config $config, array $services = []): void`
Register an application with optional initial services.
```php
Registry::registerApp($config, ['settings' => $settings]);
```

#### `Registry::add(Config $config, string $name, mixed $service): void`
Register a single service.
```php
Registry::add($config, 'mailer', new MailService());
```

#### `Registry::addMany(Config $config, array $services): void`
Register multiple services at once.
```php
Registry::addMany($config, ['api' => $api, 'cache' => $cache]);
```

#### `Registry::factory(Config $config, string $name, callable $factory): void`
Register service factory for lazy loading.
```php
Registry::factory($config, 'expensive', fn($config) => new ExpensiveService());
```

#### `Registry::get(string $app_slug, string $service_name): mixed`
Retrieve a registered service.
```php
$settings = Registry::get('my-plugin', 'settings');
```

#### `Registry::aliases(Config $config, array $aliases): void`
Create service aliases.
```php
Registry::aliases($config, ['db' => 'database', 'mail' => 'mailer']);
```

#### `Registry::getApps(): array`
Get all registered applications.
```php
$apps = Registry::getApps();
```

#### `Registry::getStats(): array`
Get registry statistics.
```php
$stats = Registry::getStats();
```

---

## Model

**Purpose**: Abstract base class for WordPress custom post types with singleton pattern, MetaBox integration, admin columns, and lifecycle management.

### Static Methods

#### `Model::get_instance(Config $config): static`
Get singleton instance of the model.
```php
$product_model = ProductModel::get_instance($config);
```

### Abstract Methods (Must be implemented)

#### `protected static function get_post_type_args(): array`
Define post type registration arguments.
```php
protected static function get_post_type_args(): array {
    return ['public' => true, 'has_archive' => true];
}
```

### Instance Methods

#### `run(bool $force_reinitialize = false): Model`
Initialize and start the model.
```php
$model->run();
```

#### `pause(): Model`
Temporarily pause the model.
```php
$model->pause();
```

#### `resume(): Model`
Resume a paused model.
```php
$model->resume();
```

#### `deactivate(): Model`
Deactivate the model and remove hooks.
```php
$model->deactivate();
```

#### `register_metabox(MetaBox $metabox): self`
Register a MetaBox with the model.
```php
$model->register_metabox($metabox);
```

#### `create(array $post_data, array $meta_data = [], bool $validate = true): int|WP_Error`
Create new post with metadata.
```php
$post_id = $model->create(['post_title' => 'New Post'], ['price' => 99.99]);
```

#### `update(int $post_id, array $post_data = [], array $meta_data = [], bool $validate = true): bool|WP_Error`
Update existing post.
```php
$model->update($post_id, ['post_title' => 'Updated'], ['price' => 89.99]);
```

#### `get_post(int $post_id, bool $include_meta = true, bool $strip_meta_key = null): ?array`
Get post with metadata.
```php
$post = $model->get_post(123, true);
```

#### `get_posts(array $args = [], bool $include_meta = false, bool $strip_meta_key = null): array`
Get multiple posts.
```php
$posts = $model->get_posts(['posts_per_page' => 10], true);
```

#### `delete(int $post_id, bool $force_delete = false): bool|WP_Error`
Delete a post.
```php
$model->delete($post_id, true);
```

#### `search(string $search_term, array $search_fields = ['title', 'content'], array $args = []): array`
Search posts by multiple criteria.
```php
$results = $model->search('WordPress', ['title', 'content', 'meta']);
```

### Admin Columns (Override in child class)

#### `protected function get_admin_columns(): array|false`
Define custom admin columns.
```php
protected function get_admin_columns(): array {
    return [
        'price' => [
            'label' => 'Price',
            'type' => 'currency',
            'sortable' => true,
            'metabox_id' => 'product_details',
            'field_id' => 'price'
        ]
    ];
}
```

---

## MetaBox

**Purpose**: Advanced custom field management with validation, lifecycle hooks, and WordPress integration.

### Static Factory Method

#### `MetaBox::create(string $id, string $title, string $screen, ?Config $config = null): MetaBox`
Create new MetaBox instance.
```php
$metabox = MetaBox::create('product_meta', 'Product Details', 'product', $config);
```

### Configuration Methods

#### `add_field(string $id, string $label, string $type, array $options = [], array $attributes = [], array $config = []): self`
Add field to the MetaBox.
```php
$metabox->add_field('price', 'Price', 'number', [], ['min' => 0, 'required' => true]);
```

#### `set_caching(bool $enable, int $duration = 3600): self`
Enable/disable field caching.
```php
$metabox->set_caching(true, 7200);
```

#### `set_prefix(string $prefix): self`
Set meta key prefix.
```php
$metabox->set_prefix('product_');
```

### Lifecycle Callbacks

#### `onSuccess(callable $callback): self`
Set success callback.
```php
$metabox->onSuccess(function($post_id, $metabox) {
    wp_cache_delete("product_{$post_id}");
});
```

#### `onError(callable $callback): self`
Set error callback.
```php
$metabox->onError(function($errors, $post_id, $metabox) {
    error_log("Validation failed: " . print_r($errors, true));
});
```

#### `onPreSave(callable $callback): self`
Set pre-save callback.
```php
$metabox->onPreSave(function($post_id, $metabox) {
    // Pre-save logic
});
```

### WordPress Integration

#### `setup_actions(): self`
Register WordPress hooks and actions.
```php
$metabox->setup_actions();
```

#### `save(int $post_id): bool|WP_Error`
Save MetaBox data.
```php
$result = $metabox->save($post_id);
```

### Data Access

#### `get_field_value(string $field_id, ?int $post_id = null, bool $single = true): mixed`
Get field value.
```php
$price = $metabox->get_field_value('price', $post_id);
```

#### `all_meta(int $post_id, string $strip = null): array`
Get all meta values.
```php
$all_data = $metabox->all_meta($post_id);
```

---

## Cache

**Purpose**: Multi-level caching system with group management, bulk operations, and WordPress transient integration.

### Static Methods

#### `Cache::set(string $key, mixed $value, ?int $expiration = null, string $group = 'default'): bool`
Set cache value.
```php
Cache::set('user_data', $data, 3600, 'users');
```

#### `Cache::get(string $key, mixed $default = false, string $group = 'default'): mixed`
Get cached value.
```php
$data = Cache::get('user_data', [], 'users');
```

#### `Cache::delete(string $key, string $group = 'default'): bool`
Delete cache entry.
```php
Cache::delete('user_data', 'users');
```

#### `Cache::remember(string $key, callable $callback, ?int $expiration = null, string $group = 'default'): mixed`
Cache or compute value.
```php
$data = Cache::remember('expensive_query', function() {
    return perform_query();
}, 3600, 'database');
```

#### `Cache::set_many(array $items, ?int $expiration = null, string $group = 'default'): array`
Set multiple cache values.
```php
Cache::set_many(['key1' => 'value1', 'key2' => 'value2'], 3600);
```

#### `Cache::get_many(array $keys, mixed $default = false, string $group = 'default'): array`
Get multiple cache values.
```php
$values = Cache::get_many(['key1', 'key2'], 'default', 'users');
```

#### `Cache::clear_group(string $group = 'default'): int`
Clear entire cache group.
```php
$cleared = Cache::clear_group('users');
```

#### `Cache::get_stats(string $group = 'default'): array`
Get cache statistics.
```php
$stats = Cache::get_stats('users');
```

---

## RestRoute

**Purpose**: Multi-version REST API management with deprecation support, middleware, and standardized responses.

### Factory Method

#### `RestRoute::create(Config|string $config_or_slug, array $supported_versions = ['v1'], string $default_version = 'v1', ?string $base_namespace = null): RestRoute`
Create REST API manager.
```php
$api = RestRoute::create($config, ['v1', 'v2'], 'v1');
```

### Route Registration

#### `addRoute(string $version, string $route, array $config): self`
Add route to specific version.
```php
$api->addRoute('v1', '/users', [
    'methods' => 'GET',
    'callback' => 'get_users_callback'
]);
```

#### `get(string $version, string $route, callable $callback, array $args = []): self`
Add GET route.
```php
$api->get('v1', '/users', function($request) {
    return get_users();
});
```

#### `post(string $version, string $route, callable $callback, array $args = []): self`
Add POST route.
```php
$api->post('v1', '/users', function($request) {
    return create_user($request->get_params());
});
```

### Version Management

#### `registerVersion(string $version, array $config = []): self`
Register API version.
```php
$api->registerVersion('v2', ['description' => 'New API version']);
```

#### `deprecateVersion(string $version, string $deprecation_date, ?string $removal_date = null, ?string $successor_version = null): self`
Deprecate API version.
```php
$api->deprecateVersion('v1', '2024-01-01', '2025-01-01', 'v2');
```

### Middleware

#### `addMiddleware(string $version, callable $middleware, int $priority = 10): self`
Add version-specific middleware.
```php
$api->addMiddleware('v1', $auth_middleware, 5);
```

#### `addGlobalMiddleware(callable $middleware, int $priority = 10): self`
Add global middleware.
```php
$api->addGlobalMiddleware($rate_limit_middleware, 10);
```

### Response Helpers

#### `successResponse(mixed $data = null, string $message = '', int $status = 200): WP_REST_Response`
Create success response.
```php
return $api->successResponse($data, 'Success', 201);
```

#### `errorResponse(string $code, string $message, mixed $data = null, int $status = 400): WP_Error`
Create error response.
```php
return $api->errorResponse('invalid_data', 'Data validation failed', $errors, 400);
```

---

## ViewLoader

**Purpose**: Template system with inheritance, caching, and multiple path support.

### Static Methods

#### `ViewLoader::load(string $view, array $data = [], bool $echo = true, ?string $base_path = null): string|false`
Load template with data.
```php
ViewLoader::load('admin/dashboard', ['stats' => $data]);
```

#### `ViewLoader::get(string $view, array $data = [], ?string $base_path = null): string`
Load template without echoing.
```php
$html = ViewLoader::get('email/template', ['user' => $user]);
```

#### `ViewLoader::exists(string $view, ?string $base_path = null): bool`
Check if template exists.
```php
if (ViewLoader::exists('custom/template')) { /* load it */ }
```

#### `ViewLoader::add_path(string $path, int $priority = 10): void`
Add template search path.
```php
ViewLoader::add_path(get_template_directory() . '/wptoolkit', 5);
```

#### `ViewLoader::set_global_data(array $data): void`
Set global template data.
```php
ViewLoader::set_global_data(['plugin_name' => 'My Plugin']);
```

#### `ViewLoader::enable_cache(int $duration = 3600, string $group = 'wptoolkit_views'): void`
Enable template caching.
```php
ViewLoader::enable_cache(7200, 'my_templates');
```

#### `ViewLoader::layout(string $layout, string $content_view, array $data = [], bool $echo = true): string|false`
Load layout with content.
```php
ViewLoader::layout('layouts/admin', 'admin/settings', $data);
```

---

## InputValidator

**Purpose**: Extensible input validation system with custom validators and error handling.

### Static Methods

#### `InputValidator::validate(string $type, mixed $value, array $field): bool|string`
Validate field value.
```php
$result = InputValidator::validate('email', 'user@example.com', ['required' => true]);
```

#### `InputValidator::register_validator(string $type, callable $validator): void`
Register custom validator.
```php
InputValidator::register_validator('isbn', function($value, $field) {
    return is_valid_isbn($value) ? true : 'Invalid ISBN';
});
```

#### `InputValidator::add_global_validator(callable $validator): void`
Add global validator for all fields.
```php
InputValidator::add_global_validator(function($value, $field, $type) {
    return !contains_profanity($value) ? true : 'Contains inappropriate content';
});
```

#### `InputValidator::validate_many(array $values, array $fields = []): array`
Validate multiple values.
```php
$results = InputValidator::validate_many([
    'email' => ['email', 'user@example.com'],
    'age' => ['number', '25']
], $field_configs);
```

#### `InputValidator::set_error_message(string $type, string $message): void`
Set custom error message.
```php
InputValidator::set_error_message('required', 'This field is required!');
```

---

## Settings

**Purpose**: WordPress Settings API integration with validation, grouping, and caching.

### Factory Method

#### `Settings::create(array $settings_config, Config $config): Settings`
Create settings manager.
```php
$settings = Settings::create([
    'api_key' => ['type' => 'text', 'required' => true]
], $config);
```

### Data Management

#### `get(string $key, mixed $default = null): mixed`
Get setting value.
```php
$api_key = $settings->get('api_key');
```

#### `set(string $key, mixed $value): bool`
Set setting value.
```php
$settings->set('api_key', 'new-key');
```

#### `getAll(?string $group = null): array`
Get all settings or group.
```php
$all = $settings->getAll();
$email_settings = $settings->getAll('email');
```

#### `import(array $data, bool $validate = true): array`
Import settings data.
```php
$results = $settings->import($imported_data, true);
```

#### `export(?string $group = null): array`
Export settings data.
```php
$exported = $settings->export('email');
```

### Rendering

#### `renderField(string $key, array $attributes = []): string`
Render form field.
```php
echo $settings->renderField('api_key', ['class' => 'regular-text']);
```

---

## Page

**Purpose**: Admin and frontend page management with template integration.

### Factory Method

#### `Page::create(Config $config, ?string $template_directory = null): Page`
Create page manager.
```php
$page = Page::create($config, '/templates');
```

### Admin Pages

#### `addMenuPage(string $slug, array $config): self`
Add admin menu page.
```php
$page->addMenuPage('dashboard', [
    'page_title' => 'My Plugin',
    'menu_title' => 'My Plugin',
    'capability' => 'manage_options',
    'callback' => 'render_dashboard'
]);
```

#### `addSubmenuPage(string $slug, array $config): self`
Add admin submenu page.
```php
$page->addSubmenuPage('settings', [
    'parent_slug' => 'dashboard',
    'page_title' => 'Settings',
    'callback' => 'render_settings'
]);
```

### Frontend Pages

#### `addFrontendPage(string $slug, array $config): self`
Add frontend page.
```php
$page->addFrontendPage('profile', [
    'title' => 'User Profile',
    'template' => 'frontend/profile.php',
    'capability' => 'read'
]);
```

### Utilities

#### `getAdminUrl(string $page_slug, array $params = []): string`
Get admin page URL.
```php
$url = $page->getAdminUrl('settings', ['tab' => 'api']);
```

#### `addDashboardWidget(string $id, string $title, callable $callback, string $capability = 'read'): self`
Add dashboard widget.
```php
$page->addDashboardWidget('stats', 'Statistics', 'render_stats', 'manage_options');
```

---

## Notification

**Purpose**: Advanced notification system with targeting, persistence, and dismissal.

### Factory Method

#### `Notification::create(Config $config, ?string $plugin_name = null): Notification`
Create notification manager.
```php
$notification = Notification::create($config, 'My Plugin');
```

### Notification Methods

#### `success(string $message, string $target = 'current', ?int $expiration = null, bool $dismissible = true): void`
Show success notification.
```php
$notification->success('Settings saved!', 'current', 300, true);
```

#### `error(string $message, string $target = 'current', ?int $expiration = null, bool $dismissible = true): void`
Show error notification.
```php
$notification->error('Save failed!', 'plugin', 600);
```

#### `warning(string $message, string $target = 'current', ?int $expiration = null, bool $dismissible = true): void`
Show warning notification.
```php
$notification->warning('Update required!', 'all');
```

#### `info(string $message, string $target = 'current', ?int $expiration = null, bool $dismissible = true): void`
Show info notification.
```php
$notification->info('New features available!', ['page1', 'page2']);
```

### Management

#### `getNotifications(): array`
Get all active notifications.
```php
$notifications = $notification->getNotifications();
```

#### `dismiss(string $notification_id): bool`
Dismiss specific notification.
```php
$notification->dismiss('notification_123');
```

#### `clear(): void`
Clear all notifications.
```php
$notification->clear();
```

---

## Autoloader

**Purpose**: PSR-4 compatible class autoloading with namespace management and plugin tracking.

### Static Methods

#### `Autoloader::init(array $namespace_map = [], array $class_map = [], bool $overwrite = false, ?string $plugin_id = null): bool`
Initialize autoloader.
```php
Autoloader::init([
    'MyPlugin\\' => __DIR__ . '/src/',
    'MyPlugin\\Models\\' => __DIR__ . '/models/'
]);
```

#### `Autoloader::register(bool $prepend = false): bool`
Register autoloader with PHP.
```php
Autoloader::register(true);
```

#### `Autoloader::add_namespace(string $namespace, string $base_dir, bool $overwrite = false, bool $prepend = false): bool`
Add namespace mapping.
```php
Autoloader::add_namespace('MyPlugin\\Utils\\', __DIR__ . '/utils/');
```

#### `Autoloader::add_class_map(string $class_name, string $file_path, bool $overwrite = false): bool`
Add direct class mapping.
```php
Autoloader::add_class_map('MyPlugin\\SpecialClass', __DIR__ . '/special.php');
```

#### `Autoloader::load_class(string $class_name): bool`
Load specific class.
```php
$loaded = Autoloader::load_class('MyPlugin\\Models\\User');
```

#### `Autoloader::can_load_class(string $class_name): bool`
Check if class can be loaded.
```php
if (Autoloader::can_load_class('MyPlugin\\Optional')) { /* use it */ }
```

#### `Autoloader::generate_class_map(string $directory, string $namespace_prefix = '', bool $recursive = true): array`
Generate class map for directory.
```php
$class_map = Autoloader::generate_class_map(__DIR__ . '/src', 'MyPlugin\\');
```

---

## APIHelper

**Purpose**: Abstract base class for HTTP requests and external API integration with caching and error handling.

### Abstract Methods (Must be implemented)

#### `protected static function get_endpoints(): array`
Define API endpoints.
```php
protected static function get_endpoints(): array {
    return [
        'users' => ['route' => '/users', 'method' => 'GET', 'cache' => true],
        'create_user' => ['route' => '/users', 'method' => 'POST', 'cache' => false]
    ];
}
```

#### `protected static function get_slug(): string`
Get plugin slug.
```php
protected static function get_slug(): string {
    return 'my-plugin';
}
```

#### `protected static function get_base_url(): string`
Get API base URL.
```php
protected static function get_base_url(): string {
    return 'https://api.example.com/v1';
}
```

#### `protected static function get_headers(): array`
Get request headers.
```php
protected static function get_headers(): array {
    return ['Authorization' => 'Bearer ' . get_option('api_token')];
}
```

### Static Methods

#### `APIHelper::request(string $method, string $url, array $params = [], array $args = []): mixed`
Make HTTP request.
```php
$response = MyAPI::request('GET', 'https://api.example.com/users');
```

#### `APIHelper::make_request(string $endpoint_name, array $params = [], array $substitutions = []): mixed`
Make request using predefined endpoint.
```php
$users = MyAPI::make_request('users', ['limit' => 10]);
```

#### `APIHelper::cache(string $key, mixed $value, ?int $expiration = null): bool`
Cache API response.
```php
MyAPI::cache('users_list', $users, 30);
```

#### `APIHelper::get_cache(string $key): mixed`
Get cached API response.
```php
$cached_users = MyAPI::get_cache('users_list');
```

#### `APIHelper::remember(string $key, callable $callback, ?int $expiration = null): mixed`
Cache or compute API response.
```php
$users = MyAPI::remember('users', function() {
    return MyAPI::make_request('users');
}, 60);
```

---

## Filesystem

**Purpose**: Comprehensive file operations and WordPress media integration with security and validation.

### Factory Method

#### `Filesystem::create(Config|string $config_or_slug, array $allowed_types = []): Filesystem`
Create filesystem manager.
```php
$fs = Filesystem::create($config, ['svg', 'webp']);
```

### File Operations

#### `getContents(string $file_path): string|false`
Read file contents.
```php
$content = $fs->getContents('/path/to/file.txt');
```

#### `putContents(string $file_path, string $contents, int $mode = 0644): bool`
Write file contents.
```php
$fs->putContents('/path/to/file.txt', 'Hello World!', 0644);
```

#### `fileExists(string $file_path): bool`
Check if file exists.
```php
if ($fs->fileExists('/path/to/file.txt')) { /* file exists */ }
```

#### `copyFile(string $source, string $destination, bool $overwrite = false): bool`
Copy file.
```php
$fs->copyFile('/source.txt', '/destination.txt', true);
```

#### `moveFile(string $source, string $destination, bool $overwrite = false): bool`
Move file.
```php
$fs->moveFile('/old.txt', '/new.txt');
```

#### `deleteFile(string $file_path): bool`
Delete file.
```php
$fs->deleteFile('/path/to/file.txt');
```

### Directory Operations

#### `createDirectory(string $dir_path, int $mode = 0755, bool $recursive = true): bool`
Create directory.
```php
$fs->createDirectory('/path/to/dir', 0755, true);
```

#### `deleteDirectory(string $dir_path, bool $recursive = false): bool`
Delete directory.
```php
$fs->deleteDirectory('/path/to/dir', true);
```

### Media Library

#### `uploadToMediaLibrary(array $file_data, string $title = '', string $description = '', int $parent_post_id = 0): int|false`
Upload file to media library.
```php
$attachment_id = $fs->uploadToMediaLibrary($_FILES['upload'], 'My File');
```

#### `getMediaFileInfo(int $attachment_id): array|false`
Get media file information.
```php
$info = $fs->getMediaFileInfo($attachment_id);
```

#### `deleteMediaFile(int $attachment_id, bool $force_delete = true): bool`
Delete media file.
```php
$fs->deleteMediaFile($attachment_id, true);
```

### File Information

#### `getFileInfo(string $file_path): array|false`
Get comprehensive file information.
```php
$info = $fs->getFileInfo('/path/to/file.txt');
```

#### `formatFileSize(int $size, int $precision = 2): string`
Format file size for display.
```php
$formatted = $fs->formatFileSize(1024000); // "1 MB"
```

#### `getMimeType(string $file_path): string|false`
Get file MIME type.
```php
$mime = $fs->getMimeType('/path/to/image.jpg');
```

---

## Requirements

**Purpose**: Validate plugin/theme requirements for PHP, WordPress, dependencies, and environment.

### Constructor

#### `new Requirements()`
Create requirements checker.
```php
$requirements = new Requirements();
```

### Validation Methods

#### `php(string $minVersion): self`
Check PHP version requirement.
```php
$requirements->php('8.1');
```

#### `wp(string $minVersion): self`
Check WordPress version requirement.
```php
$requirements->wp('5.0');
```

#### `multisite(bool $required): self`
Check multisite requirement.
```php
$requirements->multisite(true);
```

#### `plugins(array $plugins): self`
Check plugin dependencies.
```php
$requirements->plugins(['woocommerce/woocommerce.php']);
```

#### `theme(string $parentTheme): self`
Check parent theme requirement.
```php
$requirements->theme('twentytwentythree');
```

#### `packages(array $packages): self`
Check Composer package dependencies.
```php
$requirements->packages(['guzzlehttp/guzzle', 'monolog/monolog']);
```

#### `met(): bool`
Check if all requirements are met.
```php
if ($requirements->php('8.1')->wp('5.0')->met()) {
    // All requirements satisfied
}
```

### Usage Example

```php
$requirements = new Requirements();

$satisfied = $requirements
    ->php('8.1')
    ->wp('5.0')
    ->plugins(['woocommerce/woocommerce.php'])
    ->packages(['guzzlehttp/guzzle'])
    ->met();

if (!$satisfied) {
    // Display error or deactivate plugin
    add_action('admin_notices', function() {
        echo '<div class="error"><p>Requirements not met!</p></div>';
    });
    return;
}

// Continue with plugin initialization...
```

---

## Error Handling

Most WPToolkit methods return either:
- **Success values** (data, true, objects)
- **WP_Error objects** for WordPress-style error handling
- **false** for simple failure cases
- **Exception throwing** for critical errors

Always check return values and handle errors appropriately:

```php
$result = $model->create($data);
if (is_wp_error($result)) {
    error_log('Model creation failed: ' . $result->get_error_message());
} else {
    // Success - $result contains the post ID
}
```

---

## Type Safety

WPToolkit uses strict PHP 8.1+ typing. Methods expect specific types and will throw TypeError for invalid inputs. Always ensure you're passing the correct data types as documented in each method signature.