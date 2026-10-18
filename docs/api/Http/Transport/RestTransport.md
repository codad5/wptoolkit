---
schema: "wptoolkit-api/1"
title: "RestTransport"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Transport\\RestTransport"
namespace: "Codad5\\WPToolkit\\Http\\Transport"
layer: "Http"
source: "src/Http/Transport/RestTransport.php"
since: "1.0.0"
stability: "stable"
order: 24
summary: "Exposes routes on the WordPress REST API under the consumer's namespace (`my-plugin/v1`)."
related: { adr: [8] }
prev: { title: "AjaxTransport", path: "AjaxTransport.md" }
up: { title: "Http", path: "../index.md" }
---

# RestTransport

`final class Codad5\WPToolkit\Http\Transport\RestTransport`

Exposes routes on the WordPress REST API under the consumer's namespace (`my-plugin/v1`).

WordPress's `permission_callback` is left open on purpose: access is decided by the Dispatcher,
which denies a route without a rule (ADR-0008) — one place for the rule on both transports.
WordPress core still verifies the X-WP-Nonce of cookie-authenticated requests before we run.

Source `src/Http/Transport/RestTransport.php` · Since 1.0.0 · Decisions: [ADR-0008](../../../adr/0008-deny-by-default-one-http-pipeline.md)

## Methods

### `__construct()`

```php
public function __construct(Dispatcher $dispatcher, Identity $identity, ClientIp $clientIp = Codad5\WPToolkit\Support\RateLimit\ClientIp instance);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$dispatcher` | [`Dispatcher`](../Dispatcher.md) |  |
| `$identity` | [`Identity`](../../Foundation/Identity.md) |  |
| `$clientIp` | `ClientIp` |  |

### `register()`

```php
public function register(array $routes): void;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$routes` | `list<Route>` |  |

### `clientConfig()`

```php
public function clientConfig(Route $route): array;
```

Where the JS client sends this route: `{namespace}/{path}` under the REST root.

| Parameter | Type | Description |
| --- | --- | --- |
| `$route` | [`Route`](../Route.md) |  |

**Returns** `array{namespace: string, path: string}`

### `fromWordPress()`

```php
public function fromWordPress(WP_REST_Request $request): Request;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$request` | `WP_REST_Request<array<string, mixed>>` |  |

**Returns** [`Request`](../Request.md)

### `toWordPress()`

```php
public function toWordPress(Response $response): WP_REST_Response;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$response` | [`Response`](../Response.md) |  |

**Returns** `WP_REST_Response`

---

← [Previous: AjaxTransport](AjaxTransport.md) · [Up: Http](../index.md)
