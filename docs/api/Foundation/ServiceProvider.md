---
schema: "wptoolkit-api/1"
title: "ServiceProvider"
type: "class"
fqcn: "Codad5\\WPToolkit\\Foundation\\ServiceProvider"
namespace: "Codad5\\WPToolkit\\Foundation"
layer: "Foundation"
source: "src/Foundation/ServiceProvider.php"
since: "1.0.0"
stability: "stable"
order: 3
summary: "Groups the wiring for one feature."
prev: { title: "Application", path: "Application.md" }
next: { title: "Identity", path: "Identity.md" }
up: { title: "Foundation", path: "index.md" }
---

# ServiceProvider

`abstract class Codad5\WPToolkit\Foundation\ServiceProvider`

Groups the wiring for one feature.

- `register()` runs as soon as the application boots: bind services, nothing else. Don't
  resolve services or call WordPress here — other providers may not be registered yet.
- `boot()` runs on `init`, after the consumer's text domain is loaded, so it may call `__()`,
  add hooks and register routes. Declare any services it needs as parameters; they are
  resolved from the container.
- `activate()` / `deactivate()` (optional) run on plugin activation and deactivation, with
  their parameters injected the same way. Uninstall needs a static handler instead: pass
  `'uninstall' => MyUninstaller::class` in the application config.

Source `src/Foundation/ServiceProvider.php` · Since 1.0.0

## Methods

### `__construct()`

```php
public function __construct(Application $app);
```

| Parameter | Type | Description |
| --- | --- | --- |
| `$app` | [`Application`](Application.md) |  |

### `register()`

```php
public function register(): void;
```

---

← [Previous: Application](Application.md) · [Up: Foundation](index.md) · [Next: Identity](Identity.md) →
