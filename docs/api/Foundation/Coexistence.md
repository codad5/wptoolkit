---
schema: "wptoolkit-api/1"
title: "Coexistence"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\Coexistence"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/Coexistence.php"
since: "1.0.0"
stability: "stable"
order: 8
summary: "The coexistence ledger: every copy of WPToolkit loaded on the site records itself here, so a plugin can see which copies exist (`toolkit:info`, diagnostics) and explain a conflict."
related: { adr: [5] }
prev: { title: "Container", path: "Container.md" }
next: { title: "CopyOwner", path: "CopyOwner.md" }
up: { title: "Foundation", path: "index.md" }
---

# Coexistence

`final class Codad5\WPToolkit\Foundation\Coexistence`

The coexistence ledger: every copy of WPToolkit loaded on the site records itself here, so a
plugin can see which copies exist (`toolkit:info`, diagnostics) and explain a conflict.

This is the one global ADR-0005 allows. Its key is a plain string on purpose: scoping tools
rewrite namespaces, not strings, so every scoped copy writes to the same ledger.

Source `src/Foundation/Coexistence.php` · Since 1.0.0 · Decisions: [ADR-0005](../../adr/0005-multiple-copies-coexist-via-scoping.md)

## Constants

| Constant | Value | Description |
| --- | --- | --- |
| `LEDGER` | `'__wptoolkit_copies'` |  |

## Methods

### `record()`

```php
public static function record(string $version, string $path, string $namespace): void;
```

Record a copy. Recording the same path again just refreshes it.

| Parameter | Type | Description |
| --- | --- | --- |
| `$version` | `string` |  |
| `$path` | `string` |  |
| `$namespace` | `string` |  |

### `copies()`

```php
public static function copies(): array;
```

Every copy recorded so far, keyed by path.

**Returns** `array<string, array{version: string, path: string, namespace: string}>`

### `thisCopyPath()`

```php
public static function thisCopyPath(): string;
```

The package directory of the copy this class belongs to.

**Returns** `string`

### `thisCopyNamespace()`

```php
public static function thisCopyNamespace(): string;
```

The root namespace of this copy — `Codad5\WPToolkit` unscoped, `PluginA\WPToolkit` scoped.

**Returns** `string`

---

← [Previous: Container](Container.md) · [Up: Foundation](index.md) · [Next: CopyOwner](CopyOwner.md) →
