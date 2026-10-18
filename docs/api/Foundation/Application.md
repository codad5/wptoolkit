---
schema: "wptoolkit-api/1"
title: "Application"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\Application"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/Application.php"
since: "1.0.0"
stability: "stable"
order: 2
summary: "One consuming plugin or theme. Owns its container, its hooks and its providers; nothing is static, so two plugins never share state (ADR-0007)."
related: { adr: [7, 13, 18, 20] }
prev: { title: "Foundation", path: "index.md" }
next: { title: "ServiceProvider", path: "ServiceProvider.md" }
up: { title: "Foundation", path: "index.md" }
---

# Application

`final class Codad5\WPToolkit\Foundation\Application`

One consuming plugin or theme. Owns its container, its hooks and its providers; nothing is
static, so two plugins never share state (ADR-0007).

Lifecycle:
 1. `create()` — builds the config and container. No WordPress calls.
 2. `boot()` — instantiates providers and runs every `register()` straight away.
 3. On `init` (or immediately, if `init` already ran): loads the consumer's text domain, then
    runs every provider's `boot()`. Translating at this point can't trigger WordPress 6.7's
    "translation loading triggered too early" notice.
 4. `shutdown()` — removes every hook the application added (plugin deactivation).

Source `src/Foundation/Application.php` · Since 1.0.0 · Decisions: [ADR-0007](../../adr/0007-own-container-no-static-registry.md), [ADR-0013](../../adr/0013-plugins-boot-through-a-syntax-safe-guard.md), [ADR-0018](../../adr/0018-versioned-data-migrations-expand-contract.md), [ADR-0020](../../adr/0020-incompatible-unscoped-copies-refuse-activation.md)

## Constants

| Constant | Value | Description |
| --- | --- | --- |
| `VERSION` | `(set at release)` |  |

## Methods

### `onBooted()`

```php
public function onBooted(Closure $callback): void;
```

Run a callback once every provider has booted — immediately if that already happened.

| Parameter | Type | Description |
| --- | --- | --- |
| `$callback` | `Closure(): void` |  |

### `isDevelopment()`

```php
public function isDevelopment(): bool;
```

WP_DEBUG on a 'local' or 'development' site: errors surface instead of being contained, and
misconfigurations (such as a route without an access rule) throw at registration.

**Returns** `bool`

### `create()`

```php
public static function create(string $file, array $config): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$file` | `string` | The main plugin file (or the theme's functions.php). |
| `$config` | `array<string, mixed>` | Must contain a 'slug'. Optional: 'text_domain', 'domain_path' (default 'languages'), 'type' ('plugin' or 'theme', default 'plugin'). |

**Returns** `self`

### `providers()`

```php
public function providers(array $providers): self;
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$providers` | `list<class-string<ServiceProvider>>` |  |

**Returns** `self`

### `migrations()`

```php
public function migrations(array $migrations): self;
```

Versioned data migrations (ADR-0018), as objects or class names (built by the container, so
they can take services). They run on activation and on `admin_init` while any is pending.

| Parameter | Type | Description |
| --- | --- | --- |
| `$migrations` | `list<Migration\|class-string<Migration>>` |  |

**Returns** `self`

### `boot()`

```php
public function boot(): self;
```

Register every provider now; boot them on `init`.

**Returns** `self`

### `isInert()`

```php
public function isInert(): bool;
```

Whether the application refused to start because the loaded WPToolkit doesn't satisfy
`requires_toolkit` (ADR-0020).

**Returns** `bool`

### `shutdown()`

```php
public function shutdown(): void;
```

Remove every hook this application added. Call on plugin deactivation.

### `config()`

```php
public function config(): Config;
```

**Returns** [`Config`](Config.md)

### `identity()`

```php
public function identity(): Identity;
```

**Returns** [`Identity`](Identity.md)

### `container()`

```php
public function container(): Container;
```

**Returns** [`Container`](Container.md)

### `hooks()`

```php
public function hooks(): HookRegistrar;
```

**Returns** [`HookRegistrar`](HookRegistrar.md)

### `isBooted()`

```php
public function isBooted(): bool;
```

**Returns** `bool`

---

← [Previous: Foundation](index.md) · [Up: Foundation](index.md) · [Next: ServiceProvider](ServiceProvider.md) →
