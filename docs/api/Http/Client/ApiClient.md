---
schema: "wptoolkit-api/1"
title: "ApiClient"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Client\\ApiClient"
namespace: "Codad5\\WPToolkit\\Http\\Client"
layer: "Http"
source: "src/Http/Client/ApiClient.php"
since: "1.0.0"
stability: "stable"
order: 21
summary: "A client for one remote API (ports 0.x `APIHelper`): base URL, authentication, JSON, retries with backoff, cached GETs and logging — over any HttpClient (ADR-0004)."
related: { adr: [4] }
prev: { title: "Dispatcher", path: "../Dispatcher.md" }
next: { title: "Auth", path: "Auth.md" }
up: { title: "Http", path: "../index.md" }
---

# ApiClient

`final class Codad5\WPToolkit\Http\Client\ApiClient`

A client for one remote API (ports 0.x `APIHelper`): base URL, authentication, JSON, retries
with backoff, cached GETs and logging — over any HttpClient (ADR-0004).

Retries: transport failures, 429 and 5xx, up to `maxRetries`, waiting 2^attempt × base delay
(or the server's Retry-After, capped). 4xx other than 429 is never retried.

Source `src/Http/Client/ApiClient.php` · Since 1.0.0 · Decisions: [ADR-0004](../../../adr/0004-ports-and-adapters-at-real-seams.md)

## Methods

### `__construct()`

```php
public function __construct(HttpClient $http, string $baseUrl, ?Auth $auth = null, array $headers = [], int $maxRetries = 2, float $retryDelay = 0.5, float $timeout = 10.0, CacheStore $cache = Codad5\WPToolkit\Adapters\Cache\NullStore instance, Logger $logger = Codad5\WPToolkit\Adapters\Log\NullLogger instance, ?Closure $sleep = null);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$http` | `HttpClient` |  |
| `$baseUrl` | `string` |  |
| `$auth` | [`?Auth`](Auth.md) |  |
| `$headers` | `array<string, string>` | Sent with every request. |
| `$maxRetries` | `int` |  |
| `$retryDelay` | `float` |  |
| `$timeout` | `float` |  |
| `$cache` | `CacheStore` |  |
| `$logger` | `Logger` |  |
| `$sleep` | `(Closure(float): void)\|null` | How to wait between retries (seconds); injectable for tests. |

### `get()`

```php
public function get(string $path, array $query = [], ?int $cacheTtl = null): HttpResponse;
```

GET a path. With `$cacheTtl`, a successful response is cached for that many seconds.

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$query` | `array<string, scalar\|null>` |  |
| `$cacheTtl` | `?int` |  |

**Returns** `HttpResponse`

### `post()`

```php
public function post(string $path, array|string|null $body = null): HttpResponse;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$body` | `array<string, mixed>\|string\|null` |  |

**Returns** `HttpResponse`

### `put()`

```php
public function put(string $path, array|string|null $body = null): HttpResponse;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$body` | `array<string, mixed>\|string\|null` |  |

**Returns** `HttpResponse`

### `patch()`

```php
public function patch(string $path, array|string|null $body = null): HttpResponse;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$body` | `array<string, mixed>\|string\|null` |  |

**Returns** `HttpResponse`

### `delete()`

```php
public function delete(string $path): HttpResponse;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |

**Returns** `HttpResponse`

### `getJson()`

```php
public function getJson(string $path, array $query = [], ?int $cacheTtl = null): mixed;
```

Like get(), but returns the decoded JSON and throws on a non-2xx status.

| Parameter | Type | Description |
| --- | --- | --- |
| `$path` | `string` |  |
| `$query` | `array<string, scalar\|null>` |  |
| `$cacheTtl` | `?int` |  |

**Returns** `mixed`

**Throws** `HttpException`

### `send()`

```php
public function send(HttpRequest $request): HttpResponse;
```

Send with auth, timeout, retries and logging.

| Parameter | Type | Description |
| --- | --- | --- |
| `$request` | `HttpRequest` |  |

**Returns** `HttpResponse`

**Throws** `HttpException` — When every attempt failed without a response.

---

← [Previous: Dispatcher](../Dispatcher.md) · [Up: Http](../index.md) · [Next: Auth](Auth.md) →
