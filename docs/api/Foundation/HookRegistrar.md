---
schema: "wptoolkit-api/1"
title: "HookRegistrar"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\HookRegistrar"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/HookRegistrar.php"
since: "1.0.0"
stability: "stable"
order: 6
summary: "Adds WordPress actions and filters for one plugin and remembers every one, so the plugin can list them (`hooks:list`) and remove all of them on deactivation. Replaces 0.x's `add_tracked_action()`."
related: { adr: [13] }
prev: { title: "Config", path: "Config.md" }
next: { title: "Container", path: "Container.md" }
up: { title: "Foundation", path: "index.md" }
---

# HookRegistrar

`final class Codad5\WPToolkit\Foundation\HookRegistrar`

Adds WordPress actions and filters for one plugin and remembers every one, so the plugin can
list them (`hooks:list`) and remove all of them on deactivation. Replaces 0.x's
`add_tracked_action()`.

In containment mode (ADR-0013) each callback is wrapped: an exception is reported and swallowed,
an action simply stops, and a filter returns the value it was given — one broken callback can't
take the page down. On by default in production, off on local/development sites with WP_DEBUG.

Source `src/Foundation/HookRegistrar.php` · Since 1.0.0 · Decisions: [ADR-0013](../../adr/0013-plugins-boot-through-a-syntax-safe-guard.md)

## Methods

### `__construct()`

```php
public function __construct(bool $contain = false, ?Closure $report = null);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$contain` | `bool` | Wrap callbacks so exceptions are reported instead of thrown. |
| `$report` | `(Closure(Throwable, string): void)\|null` | Called with the exception and the hook name; defaults to error_log(). |

### `addAction()`

```php
public function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$hook` | `string` |  |
| `$callback` | `callable` |  |
| `$priority` | `int` |  |
| `$acceptedArgs` | `int` |  |

### `addFilter()`

```php
public function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$hook` | `string` |  |
| `$callback` | `callable` |  |
| `$priority` | `int` |  |
| `$acceptedArgs` | `int` |  |

### `removeAll()`

```php
public function removeAll(): void;
```

Remove every hook this registrar added, newest first.

### `all()`

```php
public function all(): array;
```

Every hook currently registered through this registrar, oldest first. `callback` is what the
plugin passed; `registered` is what WordPress holds (a wrapper in containment mode).

**Returns** `list<array{type: 'action'\|'filter', hook: string, callback: callable, registered: callable, priority: int, args: int}>`

### `isContaining()`

```php
public function isContaining(): bool;
```

**Returns** `bool`

---

← [Previous: Config](Config.md) · [Up: Foundation](index.md) · [Next: Container](Container.md) →
