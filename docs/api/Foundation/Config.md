---
schema: "wptoolkit-api/1"
title: "Config"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\Config"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/Config.php"
since: "1.0.0"
stability: "stable"
order: 5
summary: "Immutable configuration for one plugin or theme (replaces 0.x `Utils\\Config`)."
prev: { title: "Identity", path: "Identity.md" }
next: { title: "HookRegistrar", path: "HookRegistrar.md" }
up: { title: "Foundation", path: "index.md" }
---

# Config

`final class Codad5\WPToolkit\Foundation\Config`

Immutable configuration for one plugin or theme (replaces 0.x `Utils\Config`).

Pure value object: no WordPress calls, no magic accessors. Nested keys are read with dots:
`$config->get('cache.driver')`.

Source `src/Foundation/Config.php` · Since 1.0.0

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `$slug` (readonly) | `string` |  |
| `$file` (readonly) | `string` |  |

## Methods

### `fromArray()`

```php
public static function fromArray(string $file, array $values): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$file` | `string` | The main plugin file (or the theme's functions.php). |
| `$values` | `array<string, mixed>` | Must contain a 'slug'. |

**Returns** `self`

**Throws** `InvalidConfigException` — When the slug is missing or malformed.

### `get()`

```php
public function get(string $key, mixed $default = null): mixed;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` |  |
| `$default` | `mixed` |  |

**Returns** `mixed`

### `has()`

```php
public function has(string $key): bool;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` |  |

**Returns** `bool`

### `textDomain()`

```php
public function textDomain(): string;
```

The consumer's text domain: 'text_domain' if set, else the slug.

**Returns** `string`

### `with()`

```php
public function with(array $values): self;
```

A copy with the given values merged over this one. The slug and file can't change.

| Parameter | Type | Description |
| --- | --- | --- |
| `$values` | `array<string, mixed>` |  |

**Returns** `self`

### `all()`

```php
public function all(): array;
```

**Returns** `array<string, mixed>`

---

← [Previous: Identity](Identity.md) · [Up: Foundation](index.md) · [Next: HookRegistrar](HookRegistrar.md) →
