---
schema: "wptoolkit-api/1"
title: "Auth"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Client\\Auth"
namespace: "Codad5\\WPToolkit\\Http\\Client"
layer: "Http"
source: "src/Http/Client/Auth.php"
since: "1.0.0"
stability: "stable"
order: 22
summary: "How ApiClient authenticates each request (Strategy)."
prev: { title: "ApiClient", path: "ApiClient.md" }
next: { title: "AjaxTransport", path: "../Transport/AjaxTransport.md" }
up: { title: "Http", path: "../index.md" }
---

# Auth

`abstract class Codad5\WPToolkit\Http\Client\Auth`

How ApiClient authenticates each request (Strategy).

Source `src/Http/Client/Auth.php` · Since 1.0.0

## Methods

### `apply()`

```php
public abstract function apply(HttpRequest $request): HttpRequest;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$request` | `HttpRequest` |  |

**Returns** `HttpRequest`

### `bearer()`

```php
public static function bearer(string $token): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$token` | `string` |  |

**Returns** `self`

### `basic()`

```php
public static function basic(string $username, string $password): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$username` | `string` |  |
| `$password` | `string` |  |

**Returns** `self`

### `header()`

```php
public static function header(string $name, string $value): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |
| `$value` | `string` |  |

**Returns** `self`

---

← [Previous: ApiClient](ApiClient.md) · [Up: Http](../index.md) · [Next: AjaxTransport](../Transport/AjaxTransport.md) →
