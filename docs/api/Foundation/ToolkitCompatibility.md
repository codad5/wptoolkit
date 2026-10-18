---
schema: "wptoolkit-api/1"
title: "ToolkitCompatibility"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\ToolkitCompatibility"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/ToolkitCompatibility.php"
since: "1.0.0"
stability: "stable"
order: 13
summary: "Checks the consumer's `requires_toolkit` constraint against the WPToolkit copy that is actually loaded, and explains a conflict in terms an administrator can act on (ADR-0005, ADR-0020)."
related: { adr: [5, 20] }
prev: { title: "Psr11Bridge", path: "Psr11Bridge.md" }
next: { title: "Http", path: "../Http/index.md" }
up: { title: "Foundation", path: "index.md" }
---

# ToolkitCompatibility

`final class Codad5\WPToolkit\Foundation\ToolkitCompatibility`

Checks the consumer's `requires_toolkit` constraint against the WPToolkit copy that is actually
loaded, and explains a conflict in terms an administrator can act on (ADR-0005, ADR-0020).

With scoping this always passes — each plugin runs its own copy. It exists for unscoped installs,
where PHP loads one copy for everyone and the first one loaded wins. WordPress loads active
plugins in a fixed (alphabetical) order, so "activate this one first" is never offered as a fix.

Messages translate: build them only at render time, after `init`.

Source `src/Foundation/ToolkitCompatibility.php` · Since 1.0.0 · Decisions: [ADR-0005](../../adr/0005-multiple-copies-coexist-via-scoping.md), [ADR-0020](../../adr/0020-incompatible-unscoped-copies-refuse-activation.md)

## Methods

### `__construct()`

```php
public function __construct(Config $config);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$config` | [`Config`](Config.md) |  |

### `isCompatible()`

```php
public function isCompatible(): bool;
```

**Returns** `bool`

### `problem()`

```php
public function problem(): string;
```

One sentence: what is wrong, naming the plugin or theme whose copy was loaded.

**Returns** `string`

### `options()`

```php
public function options(): array;
```

What the administrator (or developer) can do about it, most practical first.

**Returns** `list<string>`

### `html()`

```php
public function html(): string;
```

The problem and the options as escaped HTML, for an admin notice or wp_die().

**Returns** `string`

### `pluginName()`

```php
public function pluginName(): string;
```

**Returns** `string`

---

← [Previous: Psr11Bridge](Psr11Bridge.md) · [Up: Foundation](index.md) · [Next: Http](../Http/index.md) →
