# WPToolkit

A PHP library for building WordPress plugins and themes that are secure by default, translatable,
and safe to run next to other plugins built on a different version of it.

[![CI](https://github.com/codad5/wptoolkit/actions/workflows/ci.yml/badge.svg?branch=next)](https://github.com/codad5/wptoolkit/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL_v2%2B-blue.svg)](LICENSE)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)
![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)

> **Status:** 1.0 is being finished on the `next` branch. `main` still holds 0.x — pin a tag, not
> `dev-main`. Coming from 0.x? [Migrating from 0.x](docs/guides/migrating-from-0x.md) — no data
> migration is needed.

## What it gives you

- **Boot that never takes a site down.** Your main file goes through a guard that parses on PHP 5.6:
  on an old PHP or WordPress, or if your plugin throws while starting, the site keeps working and
  administrators see why.
- **Two plugins, two versions, one site.** Everything WPToolkit puts into WordPress — hooks, option
  keys, REST namespaces, script handles, `window.wptoolkit[slug]` — is prefixed with your slug, and a
  copy scoped to your namespace can't collide with anyone else's. An incompatible copy refuses to
  activate and names the plugin that won.
- **Deny-by-default HTTP.** One pipeline serves REST, admin-ajax and public pages: every route must
  say who may call it, input is validated before your code runs, and rate limits and real HTTP status
  codes come built in. A JS client calls routes by name.
- **Data.** Fields with pluggable types, meta boxes, entities stored as post types, custom tables or
  options behind one repository contract, a query builder with capped pages, search that ORs title,
  meta and terms, admin list columns with quick edit, and versioned migrations (batched, locked,
  reversible).
- **Admin and front end.** Settings on the Settings API — sensitive values readable only by name
  and optionally encrypted — admin pages, per-user notices, PHP views with theme overrides and an
  escaper, assets enqueued only at the right moment (RTL stylesheets included), and pretty
  front-end URLs.
- **Tooling.** `wp {your-slug}` commands (`toolkit:info`, `routes:list`, `migrate`, `make:entity`, …)
  and `wptoolkit-build` for reproducible, verified release zips.
- **No runtime dependencies.** PSR-3/11/16 bridges are there if you want them.

## Requirements

PHP 8.1+, WordPress 6.4+. Composer is optional.

## Install

```bash
composer config repositories.wptoolkit vcs https://github.com/codad5/wptoolkit
```

```bash
composer require codad5/wptoolkit:^1.0
```

Without Composer, bundle the standalone zip from the GitHub release and scope it to your namespace —
see [scoping and coexistence](docs/guides/scoping-and-coexistence.md).

## Quick start

`my-books.php`:

```php
<?php
/**
 * Plugin Name: My Books
 * Requires PHP: 8.1
 * Text Domain: my-books
 */

if (!defined('ABSPATH')) {
    exit;
}

call_user_func(require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php', __FILE__, array(
    'php' => '8.1',
    'wp' => '6.4',
    'toolkit' => '^1.0',
), function () {
    \Codad5\WPToolkit\Foundation\Application::create(__FILE__, ['slug' => 'my-books'])
        ->providers([\MyBooks\BooksServiceProvider::class])
        ->boot();
});
```

`src/Book.php` and `src/BooksServiceProvider.php`:

```php
#[PostType('book', public: true, columns: ['title' => 'post_title'])]
final class Book extends Entity
{
    public static function fields(FieldFactory $f): array
    {
        return [
            $f->text('title')->label(__('Title', 'my-books'))->required(),
            $f->text('isbn')->label(__('ISBN', 'my-books'))->rules('max:17'),
        ];
    }
}

final class BooksServiceProvider extends ServiceProvider
{
    public function boot(EntityRegistrar $entities, Router $router): void
    {
        $entities->entity(Book::class, __('Book details', 'my-books'));   // post type + edit screen

        $router->get('books/{id}', [BookController::class, 'show'])
            ->public()                                                   // omit this and it won't register
            ->args(['id' => ['rules' => 'required|integer', 'type' => 'int']]);
    }
}
```

`GET /wp-json/my-books/v1/books/42` now works, with `id` validated before `BookController::show()`
runs. The [todo example](examples/todo/) is a complete plugin: settings, list columns, a front-end
page, an API and an Arabic translation.

## Documentation

| Guide | |
| --- | --- |
| [Getting started](docs/guides/getting-started.md) | A scoped plugin with an entity, a route and a translated string |
| [Routes and security](docs/guides/routes-and-security.md) | Access rules, validation, rate limits, the JS client |
| [Data](docs/guides/data.md) | Fields, meta boxes, entities, queries, search, migrations |
| [Admin and front end](docs/guides/admin-and-front-end.md) | Settings, pages, notices, views, assets, public pages |
| [Scoping and coexistence](docs/guides/scoping-and-coexistence.md) | Shipping without conflicts |
| [Migrating from 0.x](docs/guides/migrating-from-0x.md) | Class by class, from real plugins |

Design decisions are recorded as [ADRs](docs/adr/README.md); the [architecture](docs/architecture/)
and [phase plan](docs/phases/README.md) explain how it fits together. Agents: start at
[llms.txt](llms.txt).

## License

GPL-2.0-or-later.
