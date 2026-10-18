---
schema: "wptoolkit-api/1"
title: "Psr11Bridge"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\Psr11Bridge"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/Psr11Bridge.php"
since: "1.0.0"
stability: "stable"
order: 12
summary: "Exposes the application container as PSR-11, for libraries that resolve services from one."
related: { adr: [12] }
prev: { title: "LibraryTranslations", path: "LibraryTranslations.md" }
next: { title: "ToolkitCompatibility", path: "ToolkitCompatibility.md" }
up: { title: "Foundation", path: "index.md" }
---

# Psr11Bridge

`final class Codad5\WPToolkit\Foundation\Psr11Bridge` — implements `ContainerInterface`

Exposes the application container as PSR-11, for libraries that resolve services from one.

Only usable when the consumer installs `psr/container` (ADR-0012).

Source `src/Foundation/Psr11Bridge.php` · Since 1.0.0 · Decisions: [ADR-0012](../../adr/0012-zero-runtime-dependencies.md)

## Methods

### `__construct()`

```php
public function __construct(Container $container);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$container` | [`Container`](Container.md) |  |

### `get()`

```php
public function get(string $id): mixed;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$id` | `string` |  |

**Returns** `mixed`

### `has()`

```php
public function has(string $id): bool;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$id` | `string` |  |

**Returns** `bool`

---

← [Previous: LibraryTranslations](LibraryTranslations.md) · [Up: Foundation](index.md) · [Next: ToolkitCompatibility](ToolkitCompatibility.md) →
