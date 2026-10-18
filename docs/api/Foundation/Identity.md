---
schema: "wptoolkit-api/1"
title: "Identity"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\Identity"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/Identity.php"
since: "1.0.0"
stability: "stable"
order: 4
summary: "Every name the library writes into WordPress's global namespace, derived from the consumer's slug, so two plugins — or two copies of WPToolkit — never collide (ADR-0005, ADR-0006)."
related: { adr: [5, 6, 16] }
prev: { title: "ServiceProvider", path: "ServiceProvider.md" }
next: { title: "Config", path: "Config.md" }
up: { title: "Foundation", path: "index.md" }
---

# Identity

`final class Codad5\WPToolkit\Foundation\Identity`

Every name the library writes into WordPress's global namespace, derived from the consumer's
slug, so two plugins — or two copies of WPToolkit — never collide (ADR-0005, ADR-0006).

Option and meta keys keep 0.x's exact shapes, because sites already store data under them
(ADR-0016). Everything else follows ADR-0006.

Library code never writes one of these names as a string literal; it asks Identity.

Source `src/Foundation/Identity.php` · Since 1.0.0 · Decisions: [ADR-0005](../../adr/0005-multiple-copies-coexist-via-scoping.md), [ADR-0006](../../adr/0006-library-text-domain-and-consumer-prefixes.md), [ADR-0016](../../adr/0016-1-0-reads-0x-data-unchanged.md)

## Constants

| Constant | Value | Description |
| --- | --- | --- |
| `JS_NAMESPACE` | `'wptoolkit'` | The one shared JavaScript namespace: `window.wptoolkit`. Every plugin's data lives under its own slug key inside it, never at the top level (ADR-0006 amendment). Writers must merge (`window.wptoolkit = window.wptoolkit \|\| {}`), never replace. |

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `$slug` (readonly) | `string` |  |

## Methods

### `__construct()`

```php
public function __construct(string $slug);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$slug` | `string` |  |

### `hook()`

```php
public function hook(string $name): string;
```

An action or filter name: `my-plugin/http/before_dispatch`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `ajaxAction()`

```php
public function ajaxAction(string $name): string;
```

An admin-ajax action: `my_plugin_books_search`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `shortcodeTag()`

```php
public function shortcodeTag(string $name): string;
```

A shortcode tag: `my_plugin_widget`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `queryVar()`

```php
public function queryVar(string $name): string;
```

A public query variable (rewrite rules): `my_plugin_page`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `restNamespace()`

```php
public function restNamespace(string $version = 'v1'): string;
```

A REST namespace: `my-plugin/v1`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$version` | `string` |  |

**Returns** `non-falsy-string`

### `optionKey()`

```php
public function optionKey(string $key): string;
```

An option name, in 0.x's shape: `{slug}_{sanitize_key(key)}` — e.g. `member-directory_api_key`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` |  |

**Returns** `non-empty-string`

**Throws** `InvalidConfigException` — When the result is longer than the options table allows.

### `transientKey()`

```php
public function transientKey(string $key, bool $site = false): string;
```

A transient name. Long keys are shortened with a hash so they always fit.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` |  |
| `$site` | `bool` |  |

**Returns** `non-empty-string`

### `metaKey()`

```php
public function metaKey(string $boxId, string $postType, string $field, ?string $prefix = null): string;
```

A post meta key, in 0.x MetaBox's shape: `{box_id}_{post_type}_{field}`.

Pass `$prefix` when the box was given a custom prefix (0.x `set_prefix()`); it then replaces
`{box_id}_{post_type}_` entirely, exactly as 0.x did — including 0.x's sanitize_key() of it, so
`'Acme_'` still means `acme_`. An empty prefix means the bare field name.

| Parameter | Type | Description |
| --- | --- | --- |
| `$boxId` | `string` |  |
| `$postType` | `string` |  |
| `$field` | `string` |  |
| `$prefix` | `?string` |  |

**Returns** `non-empty-string`

### `table()`

```php
public function table(string $name): string;
```

A custom table name, without `$wpdb->prefix`: `my_plugin_books`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `cronHook()`

```php
public function cronHook(string $name): string;
```

A WP-Cron hook: `my_plugin_cleanup`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `handle()`

```php
public function handle(string $name): string;
```

A script or style handle: `my-plugin-toolkit-api`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

### `jsKey()`

```php
public function jsKey(): string;
```

This consumer's key inside `window.wptoolkit` — its slug, unique per site.

**Returns** `string`

### `jsAccessor()`

```php
public function jsAccessor(): string;
```

A JavaScript expression for this consumer's data: `window.wptoolkit["my-plugin"]`.

**Returns** `non-empty-string`

### `nonceAction()`

```php
public function nonceAction(string $name): string;
```

A nonce action: `my-plugin:books.search`.

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `non-empty-string`

---

← [Previous: ServiceProvider](ServiceProvider.md) · [Up: Foundation](index.md) · [Next: Config](Config.md) →
