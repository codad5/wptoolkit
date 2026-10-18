---
schema: "wptoolkit-api/1"
title: "Container"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\Container"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/Container.php"
since: "1.0.0"
stability: "stable"
order: 7
summary: "The library's own container (ADR-0007): bindings, singletons, constructor autowiring, method injection, and an error that names the cycle when entries depend on each other."
related: { adr: [7] }
prev: { title: "HookRegistrar", path: "HookRegistrar.md" }
next: { title: "Coexistence", path: "Coexistence.md" }
up: { title: "Foundation", path: "index.md" }
---

# Container

`final class Codad5\WPToolkit\Foundation\Container` — implements `Codad5\WPToolkit\Contracts\Container\Container`

The library's own container (ADR-0007): bindings, singletons, constructor autowiring,
method injection, and an error that names the cycle when entries depend on each other.

One instance per Application, so two plugins never share state.

Source `src/Foundation/Container.php` · Since 1.0.0 · Decisions: [ADR-0007](../../adr/0007-own-container-no-static-registry.md)

## Methods

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

### `bind()`

```php
public function bind(string $id, ?Closure $factory = null): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$id` | `string` |  |
| `$factory` | `?Closure` |  |

### `singleton()`

```php
public function singleton(string $id, ?Closure $factory = null): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$id` | `string` |  |
| `$factory` | `?Closure` |  |

### `instance()`

```php
public function instance(string $id, object $instance): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$id` | `string` |  |
| `$instance` | `object` |  |

### `call()`

```php
public function call(callable $callable, array $parameters = []): mixed;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$callable` | `callable` |  |
| `$parameters` | `array` |  |

**Returns** `mixed`

---

← [Previous: HookRegistrar](HookRegistrar.md) · [Up: Foundation](index.md) · [Next: Coexistence](Coexistence.md) →
