---
schema: "wptoolkit-api/1"
title: "Request"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Request"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
source: "src/Http/Request.php"
since: "1.0.0"
stability: "stable"
order: 17
summary: "An incoming request, the same shape whether it arrived over REST or admin-ajax (immutable)."
prev: { title: "Route", path: "Route.md" }
next: { title: "Response", path: "Response.md" }
up: { title: "Http", path: "index.md" }
---

# Request

`final class Codad5\WPToolkit\Http\Request`

An incoming request, the same shape whether it arrived over REST or admin-ajax (immutable).

Input is already unslashed. After validation, `input()` returns the sanitized values the route
declared; undeclared input stays reachable through `raw()` for the rare case that needs it.

Source `src/Http/Request.php` · Since 1.0.0

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `$method` (readonly) | `string` |  |
| `$transport` (readonly) | `'rest'\|'ajax'\|'page'` | Where the request came in ('page': a PublicPages URL). |
| `$routeParams` (readonly) | `array<string, mixed>` | Values captured from the route path. |
| `$query` (readonly) | `array<string, mixed>` |  |
| `$body` (readonly) | `array<string, mixed>` |  |
| `$headers` (readonly) | `array<string, string>` | Lowercase names. |
| `$files` (readonly) | `array<string, mixed>` |  |
| `$userId` (readonly) | `int` |  |
| `$ip` (readonly) | `string` |  |

## Methods

### `__construct()`

```php
public function __construct(string $method, string $transport, array $routeParams = [], array $query = [], array $body = [], array $headers = [], array $files = [], int $userId = 0, string $ip = '0.0.0.0', ?array $validated = null);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$method` | `string` |  |
| `$transport` | `'rest'\|'ajax'\|'page'` | Where the request came in ('page': a PublicPages URL). |
| `$routeParams` | `array<string, mixed>` | Values captured from the route path. |
| `$query` | `array<string, mixed>` |  |
| `$body` | `array<string, mixed>` |  |
| `$headers` | `array<string, string>` | Lowercase names. |
| `$files` | `array<string, mixed>` |  |
| `$userId` | `int` |  |
| `$ip` | `string` |  |
| `$validated` | `array<string, mixed>` | Set by the dispatcher after validation. |

### `input()`

```php
public function input(string $key, mixed $default = null): mixed;
```

A declared, validated and sanitized input value — route params, then body, then query.

| Parameter | Type | Description |
| --- | --- | --- |
| `$key` | `string` |  |
| `$default` | `mixed` |  |

**Returns** `mixed`

### `validated()`

```php
public function validated(): array;
```

Every declared, validated and sanitized input.

**Returns** `array<string, mixed>`

### `raw()`

```php
public function raw(): array;
```

All input as received (unslashed, not sanitized): route params override body override query.

**Returns** `array<string, mixed>`

### `header()`

```php
public function header(string $name): ?string;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `?string`

### `isLoggedIn()`

```php
public function isLoggedIn(): bool;
```

**Returns** `bool`

### `withValidated()`

```php
public function withValidated(array $validated): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$validated` | `array<string, mixed>` |  |

**Returns** `self`

---

← [Previous: Route](Route.md) · [Up: Http](index.md) · [Next: Response](Response.md) →
