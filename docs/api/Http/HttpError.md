---
schema: "wptoolkit-api/1"
title: "HttpError"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\HttpError"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
source: "src/Http/HttpError.php"
since: "1.0.0"
stability: "stable"
order: 19
summary: "An error meant for the client: it carries the HTTP status, a stable machine-readable code and a message safe to show. Anything else thrown by a controller becomes a generic 500 (ADR-0008)."
related: { adr: [8] }
prev: { title: "Response", path: "Response.md" }
next: { title: "Dispatcher", path: "Dispatcher.md" }
up: { title: "Http", path: "index.md" }
---

# HttpError

`class Codad5\WPToolkit\Http\HttpError` — extends `RuntimeException`, implements `Throwable`, `WPToolkitException`

An error meant for the client: it carries the HTTP status, a stable machine-readable code and a
message safe to show. Anything else thrown by a controller becomes a generic 500 (ADR-0008).

Throw these from controllers and middleware: `throw HttpError::notFound()`.

Source `src/Http/HttpError.php` · Since 1.0.0 · Decisions: [ADR-0008](../../adr/0008-deny-by-default-one-http-pipeline.md)

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `$status` (readonly) | `int` |  |
| `$errorCode` (readonly) | `string` |  |
| `$details` (readonly) | `array<string, mixed>` | Extra, client-safe data (e.g. validation errors). |
| `$headers` (readonly) | `array<string, string>` |  |

## Methods

### `__construct()`

```php
public function __construct(int $status, string $errorCode, string $message, array $details = [], array $headers = [], ?Throwable $previous = null);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$status` | `int` |  |
| `$errorCode` | `string` |  |
| `$message` | `string` |  |
| `$details` | `array<string, mixed>` | Extra, client-safe data (e.g. validation errors). |
| `$headers` | `array<string, string>` |  |
| `$previous` | `?Throwable` |  |

### `badRequest()`

```php
public static function badRequest(?string $message = null): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$message` | `?string` |  |

**Returns** `self`

### `unauthenticated()`

```php
public static function unauthenticated(?string $message = null): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$message` | `?string` |  |

**Returns** `self`

### `forbidden()`

```php
public static function forbidden(?string $message = null): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$message` | `?string` |  |

**Returns** `self`

### `notFound()`

```php
public static function notFound(?string $message = null): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$message` | `?string` |  |

**Returns** `self`

### `validation()`

```php
public static function validation(array $errors): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$errors` | `array<string, list<string>>` | Field => messages. |

**Returns** `self`

### `tooManyRequests()`

```php
public static function tooManyRequests(int $retryAfter, array $headers = []): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$retryAfter` | `int` |  |
| `$headers` | `array<string, string>` |  |

**Returns** `self`

### `internal()`

```php
public static function internal(): self;
```

**Returns** `self`

---

← [Previous: Response](Response.md) · [Up: Http](index.md) · [Next: Dispatcher](Dispatcher.md) →
