# Getting started

From nothing to a running plugin with one entity, one route and one translated string. You need PHP
8.1+, Composer, Node 20 and Docker (for `wp-env`).

## 1. Create the plugin

```bash
mkdir my-books && cd my-books
```

```bash
composer init --name=me/my-books --type=wordpress-plugin --autoload=src/ --no-interaction
```

Set the namespace in `composer.json` to `"MyBooks\\": "src/"`, then add WPToolkit:

```bash
composer config repositories.wptoolkit vcs https://github.com/codad5/wptoolkit
```

```bash
composer require codad5/wptoolkit:^1.0
```

## 2. The main file

`my-books.php` — plain PHP 5.6 syntax on purpose: on a server that is too old, this file still parses,
the guard shows administrators a notice, and the site keeps working.

```php
<?php
/**
 * Plugin Name: My Books
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * Text Domain: my-books
 */

if (!defined('ABSPATH')) {
    exit;
}

call_user_func(require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php', __FILE__, array(
    'php' => '8.1',
    'wp' => '6.4',
    'toolkit' => '^1.0',
    'name' => 'My Books',
), function () {
    \Codad5\WPToolkit\Foundation\Application::create(__FILE__, ['slug' => 'my-books'])
        ->providers([\MyBooks\BooksServiceProvider::class])
        ->boot();
});
```

The slug prefixes everything the plugin puts into WordPress. Pick it once; changing it later changes
your option keys.

## 3. Generate an entity and a controller

```bash
npx @wordpress/env start
```

```bash
npx wp-env run cli wp plugin activate my-books
```

```bash
npx wp-env run cli wp my-books make:entity Book
```

```bash
npx wp-env run cli wp my-books make:controller Book
```

```bash
npx wp-env run cli wp my-books make:provider Books
```

(`wp-env` mounts the current directory as a plugin automatically.)

## 4. Wire them up

`src/BooksServiceProvider.php`:

```php
public function boot(EntityRegistrar $entities, Router $router): void
{
    $entities->entity(Entities\Book::class, __('Book details', 'my-books'));

    $router->get('books', [Http\BookController::class, 'index'])->public();
    $router->get('books/{id}', [Http\BookController::class, 'show'])
        ->public()
        ->args(['id' => ['rules' => 'required|integer', 'type' => 'int']]);
}
```

- `__('Book details', 'my-books')` is your translated string: `boot()` runs on `init`, after your
  text domain is loaded, so it never triggers WordPress's "translation loaded too early" notice.
- Remove `->public()` and the route refuses to register: every route states who may call it.

Visit **Books** in wp-admin, add a book, then open `/wp-json/my-books/v1/books/1`.

## 5. Check it

```bash
npx wp-env run cli wp my-books routes:list
```

Every route shows its access rule. `wp my-books toolkit:info` shows which WPToolkit copy you run
and any other copies on the site.

## 6. Ship it

Build a release zip with `wptoolkit-build` (`wptoolkit-build init`, then `wptoolkit-build package`).
If your plugin might share a site with another WPToolkit plugin, scope your copy first — see
[scoping and coexistence](scoping-and-coexistence.md).

Next: [routes and security](routes-and-security.md), [data](data.md),
[admin and front end](admin-and-front-end.md).
