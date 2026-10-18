---
schema: "wptoolkit-api/1"
title: "Route"
type: "class"
fqcn: "Codad5\\WPToolkit\\Http\\Route"
namespace: "Codad5\\WPToolkit\\Http"
layer: "Http"
source: "src/Http/Route.php"
since: "1.0.0"
stability: "stable"
order: 16
summary: "One route, built fluently (Builder). Declares who may call it — there is no default (ADR-0008) — and how it's exposed."
related: { adr: [8] }
prev: { title: "Router", path: "Router.md" }
next: { title: "Request", path: "Request.md" }
up: { title: "Http", path: "index.md" }
---

# Route

`final class Codad5\WPToolkit\Http\Route`

One route, built fluently (Builder). Declares who may call it — there is no default
(ADR-0008) — and how it's exposed.

    $router->get('books/{id}', [BookController::class, 'show'])
        ->can('read')
        ->args(['id' => ['rules' => 'required|integer', 'type' => 'int']])
        ->rateLimit(60, perSeconds: 60)
        ->exposeVia('rest', 'ajax');

Source `src/Http/Route.php` · Since 1.0.0 · Decisions: [ADR-0008](../../adr/0008-deny-by-default-one-http-pipeline.md)

## Properties

| Property | Type | Description |
| --- | --- | --- |
| `$methods` (readonly) | `list<string>` |  |
| `$path` (readonly) | `string` |  |

## Methods

### `__construct()`

```php
public function __construct(array $methods, string $path, mixed $handler);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$methods` | `list<string>` |  |
| `$path` | `string` |  |
| `$handler` | `callable\|array{class-string, string}` |  |

### `public()`

```php
public function public(): self;
```

Anyone, including logged-out visitors. The one explicit way to make a route open.

**Returns** `self`

### `loggedIn()`

```php
public function loggedIn(): self;
```

**Returns** `self`

### `can()`

```php
public function can(string $capability): self;
```

Logged in and holding a capability (or meta capability).

| Parameter | Type | Description |
| --- | --- | --- |
| `$capability` | `string` |  |

**Returns** `self`

### `authorize()`

```php
public function authorize(Closure $policy): self;
```

Custom authorization, e.g. "may edit this specific post".

| Parameter | Type | Description |
| --- | --- | --- |
| `$policy` | `Closure(Request): bool` |  |

**Returns** `self`

### `args()`

```php
public function args(array $args): self;
```

Declared input: only these fields reach `Request::input()`, validated and sanitized.

| Parameter | Type | Description |
| --- | --- | --- |
| `$args` | `array<string, array{rules?: string\|list<string\|Rule>, type?: string, label?: string, default?: mixed}>` |  |

**Returns** `self`

### `rateLimit()`

```php
public function rateLimit(int $max, int $perSeconds = 60, string $by = 'ip'): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$max` | `int` |  |
| `$perSeconds` | `int` |  |
| `$by` | `'ip'\|'user'` | Bucket per client IP or per logged-in user (falls back to IP). |

**Returns** `self`

### `exposeVia()`

```php
public function exposeVia(string ...$transports): self;
```

Where the route is reachable: 'rest' (default), 'ajax', or both.

| Parameter | Type | Description |
| --- | --- | --- |
| `$transports` | `string` |  |

**Returns** `self`

### `middleware()`

```php
public function middleware(Middleware|string ...$middleware): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$middleware` | `Middleware\|class-string<Middleware>` |  |

**Returns** `self`

### `version()`

```php
public function version(string $version): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$version` | `string` |  |

**Returns** `self`

### `name()`

```php
public function name(string $name): self;
```

The name used for the Ajax action and nonce; defaults to the path (`books/{id}` → `books_id`).

| Parameter | Type | Description |
| --- | --- | --- |
| `$name` | `string` |  |

**Returns** `self`

### `access()`

```php
public function access(): ?string;
```

**Returns** `?string`

### `capability()`

```php
public function capability(): ?string;
```

**Returns** `?string`

### `policy()`

```php
public function policy(): ?Closure;
```

**Returns** `(Closure(Request): bool)\|null`

### `declaredArgs()`

```php
public function declaredArgs(): array;
```

**Returns** `array<string, array{rules?: string\|list<string\|Rule>, type?: string, label?: string, default?: mixed}>`

### `rateLimitConfig()`

```php
public function rateLimitConfig(): ?array;
```

**Returns** `array{max: int, seconds: int, by: string}\|null`

### `transports()`

```php
public function transports(): array;
```

**Returns** `list<'rest'\|'ajax'>`

### `middlewareStack()`

```php
public function middlewareStack(): array;
```

**Returns** `list<Middleware\|class-string<Middleware>>`

### `apiVersion()`

```php
public function apiVersion(): string;
```

**Returns** `string`

### `routeName()`

```php
public function routeName(): string;
```

**Returns** `string`

### `handler()`

```php
public function handler(): mixed;
```

**Returns** `callable\|array{class-string, string}`

### `isMutation()`

```php
public function isMutation(): bool;
```

**Returns** `bool`

### `restPattern()`

```php
public function restPattern(): string;
```

The path as a WordPress REST regex: `books/{id}` → `/books/(?P<id>[^/]+)`.

**Returns** `non-falsy-string`

---

← [Previous: Router](Router.md) · [Up: Http](index.md) · [Next: Request](Request.md) →
