---
schema: "wptoolkit-api/1"
title: "Response"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Response"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
source: "src/Http/Response.php"
since: "1.0.0"
stability: "stable"
order: 18
summary: "An outgoing response, sent as JSON by either transport (immutable)."
prev: { title: "Request", path: "Request.md" }
next: { title: "HttpError", path: "HttpError.md" }
up: { title: "Http", path: "index.md" }
---

# Response

`final class Codad5\WPToolkit\Http\Response`

An outgoing response, sent as JSON by either transport (immutable).

Success bodies are the data itself. Error bodies always have the same shape on both transports:
`{ "code": "...", "message": "...", "details": {...}? }`.

Source `src/Http/Response.php` · Since 1.0.0

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `$data` (readonly) | `mixed` |  |
| `$status` (readonly) | `int` |  |
| `$headers` (readonly) | `array<string, string>` |  |

## Methods

### `__construct()`

```php
public function __construct(mixed $data = null, int $status = 200, array $headers = []);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$data` | `mixed` |  |
| `$status` | `int` |  |
| `$headers` | `array<string, string>` |  |

### `json()`

```php
public static function json(mixed $data, int $status = 200, array $headers = []): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$data` | `mixed` |  |
| `$status` | `int` |  |
| `$headers` | `array<string, string>` |  |

**Returns** `self`

### `created()`

```php
public static function created(mixed $data = null): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$data` | `mixed` |  |

**Returns** `self`

### `noContent()`

```php
public static function noContent(): self;
```

**Returns** `self`

### `error()`

```php
public static function error(int $status, string $code, string $message, array $details = [], array $headers = []): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$status` | `int` |  |
| `$code` | `string` |  |
| `$message` | `string` |  |
| `$details` | `array<string, mixed>` |  |
| `$headers` | `array<string, string>` |  |

**Returns** `self`

### `fromError()`

```php
public static function fromError(HttpError $error): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$error` | [`HttpError`](HttpError.md) |  |

**Returns** `self`

### `from()`

```php
public static function from(mixed $result): self;
```

Turn whatever a controller returned into a Response.

| Parameter | Type | Description |
| --- | --- | --- |
| `$result` | `mixed` |  |

**Returns** `self`

### `withHeaders()`

```php
public function withHeaders(array $headers): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$headers` | `array<string, string>` |  |

**Returns** `self`

### `isError()`

```php
public function isError(): bool;
```

**Returns** `bool`

---

← [Previous: Request](Request.md) · [Up: Http](index.md) · [Next: HttpError](HttpError.md) →
