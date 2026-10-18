---
schema: "wptoolkit-api/1"
title: "AjaxTransport"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Transport\\AjaxTransport"
namespace: "Codad5\\WPToolkit\\Http\\Transport"
layer: "Http"
source: "src/Http/Transport/AjaxTransport.php"
since: "1.0.0"
stability: "stable"
order: 23
summary: "Exposes routes through admin-ajax as `{slug}_{route}` actions. Logged-out access (`wp_ajax_nopriv_…`) is registered only for `->public()` routes; the Dispatcher still checks."
prev: { title: "Auth", path: "../Client/Auth.md" }
next: { title: "RestTransport", path: "RestTransport.md" }
up: { title: "Http", path: "../index.md" }
---

# AjaxTransport

`final class Codad5\WPToolkit\Http\Transport\AjaxTransport`

Exposes routes through admin-ajax as `{slug}_{route}` actions. Logged-out access
(`wp_ajax_nopriv_…`) is registered only for `->public()` routes; the Dispatcher still checks.

Responses send the real HTTP status (0.x always sent 200), with the same body shape as REST.

Source `src/Http/Transport/AjaxTransport.php` · Since 1.0.0

## Methods

### `__construct()`

```php
public function __construct(Dispatcher $dispatcher, Identity $identity, HookRegistrar $hooks, ClientIp $clientIp = Codad5\WPToolkit\Support\RateLimit\ClientIp instance, ?Closure $send = null);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$dispatcher` | [`Dispatcher`](../Dispatcher.md) |  |
| `$identity` | [`Identity`](../../Foundation/Identity.md) |  |
| `$hooks` | [`HookRegistrar`](../../Foundation/HookRegistrar.md) |  |
| `$clientIp` | `ClientIp` |  |
| `$send` | `(Closure(Response): void)\|null` | How to emit the response; defaults to wp_send_json(). |

### `register()`

```php
public function register(array $routes): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$routes` | `list<Route>` |  |

### `handle()`

```php
public function handle(Route $route, Route ...$siblings): void;
```

Dispatch to whichever of the action's routes accepts the request method; 405 if none does.

| Parameter | Type | Description |
| --- | --- | --- |
| `$route` | [`Route`](../Route.md) |  |
| `$siblings` | [`Route`](../Route.md) |  |

### `clientConfig()`

```php
public function clientConfig(Route $route): array;
```

The action's name and nonce, for the JS client (Phase 5 localizes these).

| Parameter | Type | Description |
| --- | --- | --- |
| `$route` | [`Route`](../Route.md) |  |

**Returns** `array{action: string, nonce: string}`

---

← [Previous: Auth](../Client/Auth.md) · [Up: Http](../index.md) · [Next: RestTransport](RestTransport.md) →
