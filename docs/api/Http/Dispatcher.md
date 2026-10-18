---
schema: "wptoolkit-api/1"
title: "Dispatcher"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Dispatcher"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
source: "src/Http/Dispatcher.php"
since: "1.0.0"
stability: "stable"
order: 20
summary: "The one request pipeline both transports use (ADR-0008), so every security rule is written once:"
related: { adr: [8] }
prev: { title: "HttpError", path: "HttpError.md" }
next: { title: "ApiClient", path: "Client/ApiClient.md" }
up: { title: "Http", path: "index.md" }
---

# Dispatcher

`final class Codad5\WPToolkit\Http\Dispatcher`

The one request pipeline both transports use (ADR-0008), so every security rule is written once:

  access rule → nonce (Ajax mutations) → rate limit → validation & sanitization → route
  middleware → controller → response

Errors: an HttpError becomes its own status and safe message; anything else is logged with a
request ID and becomes a generic 500. Details reach the client only for administrators with
WP_DEBUG on.

Source `src/Http/Dispatcher.php` · Since 1.0.0 · Decisions: [ADR-0008](../../adr/0008-deny-by-default-one-http-pipeline.md)

## Methods

### `__construct()`

```php
public function __construct(Container $container, Identity $identity, Logger $logger, ?RateLimiter $rateLimiter = null, bool $developmentMode = false);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$container` | [`Container`](../Foundation/Container.md) |  |
| `$identity` | [`Identity`](../Foundation/Identity.md) |  |
| `$logger` | `Logger` |  |
| `$rateLimiter` | `?RateLimiter` |  |
| `$developmentMode` | `bool` |  |

### `dispatch()`

```php
public function dispatch(Route $route, Request $request): Response;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$route` | [`Route`](Route.md) |  |
| `$request` | [`Request`](Request.md) |  |

**Returns** [`Response`](Response.md)

---

← [Previous: HttpError](HttpError.md) · [Up: Http](index.md) · [Next: ApiClient](Client/ApiClient.md) →
