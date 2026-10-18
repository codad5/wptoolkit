---
schema: "wptoolkit-api/1"
title: "Router"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Router"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
source: "src/Http/Router.php"
since: "1.0.0"
stability: "stable"
order: 15
summary: "Collects routes and registers them with WordPress — REST and/or admin-ajax — through the same Dispatcher (ADR-0008). Replaces 0.x `Ajax` and `RestRoute`."
related: { adr: [8] }
prev: { title: "Http", path: "index.md" }
next: { title: "Route", path: "Route.md" }
up: { title: "Http", path: "index.md" }
---

# Router

`final class Codad5\WPToolkit\Http\Router`

Collects routes and registers them with WordPress — REST and/or admin-ajax — through the same
Dispatcher (ADR-0008). Replaces 0.x `Ajax` and `RestRoute`.

Source `src/Http/Router.php` · Since 1.0.0 · Decisions: [ADR-0008](../../adr/0008-deny-by-default-one-http-pipeline.md)

## Methods

### `__construct()`

```php
public function __construct(HookRegistrar $hooks, RestTransport $rest, AjaxTransport $ajax, bool $developmentMode = false);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$hooks` | [`HookRegistrar`](../Foundation/HookRegistrar.md) |  |
| `$rest` | [`RestTransport`](Transport/RestTransport.md) |  |
| `$ajax` | [`AjaxTransport`](Transport/AjaxTransport.md) |  |
| `$developmentMode` | `bool` |  |

### `get()`

```php
public function get(string $path, callable|array $handler): Route;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

**Returns** [`Route`](Route.md)

### `post()`

```php
public function post(string $path, callable|array $handler): Route;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

**Returns** [`Route`](Route.md)

### `put()`

```php
public function put(string $path, callable|array $handler): Route;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

**Returns** [`Route`](Route.md)

### `patch()`

```php
public function patch(string $path, callable|array $handler): Route;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

**Returns** [`Route`](Route.md)

### `delete()`

```php
public function delete(string $path, callable|array $handler): Route;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

**Returns** [`Route`](Route.md)

### `add()`

```php
public function add(array $methods, string $path, callable|array $handler): Route;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$methods` | `list<string>` |  |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

**Returns** [`Route`](Route.md)

### `register()`

```php
public function register(): void;
```

Hook the transports. Called once the providers have booted.

### `clientConfig()`

```php
public function clientConfig(): array;
```

What the JS client needs to call every route by name (`api.call('todos', …)`): URLs, the REST
nonce and, per route, its methods and REST path or Ajax action and nonce. REST is used when a
route offers it. Build this while enqueuing — nonces belong to the current user.

**Returns** `array{restUrl: string, restNonce: string, ajaxUrl: string, routes: array<string, array<string, mixed>>}`

### `routes()`

```php
public function routes(): array;
```

Every route, for `routes:list` and tests.

**Returns** `list<Route>`

---

← [Previous: Http](index.md) · [Up: Http](index.md) · [Next: Route](Route.md) →
