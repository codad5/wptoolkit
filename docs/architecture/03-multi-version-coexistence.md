# 03 — Multi-version coexistence

> **Load this before touching anything global to WordPress.** Decisions:
> [ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md),
> [ADR-0006](../adr/0006-library-text-domain-and-consumer-prefixes.md),
> [ADR-0014](../adr/0014-composer-is-optional.md).

## The problem in one picture

```
            one WordPress site, one PHP process
┌──────────────────────────┐    ┌──────────────────────────┐
│ plugin-a                 │    │ plugin-b                 │
│  vendor/ WPToolkit 1.2   │    │  vendor/ WPToolkit 1.4   │
└────────────┬─────────────┘    └────────────┬─────────────┘
             │  class Codad5\WPToolkit\Foundation\Application
             └──────────────▶  PHP loads it ONCE  ◀──────────┘
                     whoever autoloads first wins; the other runs untested code

 …and even with different class names, both share WordPress's globals:
 hooks · ajax actions · REST namespaces · options · transients · tables · cron · handles · window.*
```

## The three layers of defence

| Layer | Mechanism | Protects against |
| ----- | --------- | ---------------- |
| 1. Scoping | Strauss / PHP-Scoper (Composer) or `bin/scope.php` (standalone) rewrite `Codad5\WPToolkit` → `PluginA\WPToolkit` | PHP class collisions |
| 2. Consumer-prefixed globals | `Foundation\Identity` derives every WordPress-global name from the plugin's slug | WordPress-level collisions, even between scoped copies |
| 3. Detection | Coexistence ledger + `requires_toolkit` constraint + the guard | Unscoped copies: the plugin goes inert with a notice instead of running the wrong version |

## Rules for library code

1. **No class names in strings.** Use `::class`. Scopers rewrite code, not arbitrary strings.
2. **No hard-coded WordPress-global names.** Get them from `Identity`. The only exceptions are the
   ledger and the `wptoolkit/loaded` diagnostic action.
3. **No global functions or constants**, except inside `bootstrap/` files which `return` closures.
4. **No static properties that hold data.** Two plugins sharing an unscoped copy would share them.
5. **JS:** no shared `window.wpToolkit`; localized data under `Identity::jsGlobal()`; script handles
   via `Identity::handle()`.

## The coexistence ledger

The one sanctioned global. Each copy, at load:

```php
$GLOBALS['__wptoolkit_copies'][] = ['version' => '1.2.0', 'path' => __DIR__, 'namespace' => __NAMESPACE__];
```

Used by `requires_toolkit` checks, by `wp {slug} toolkit:info`, and by the admin diagnostic notice
listing every copy on the site.

## How it's tested

The Phase 1 coexistence E2E runs four fixture plugins on one wp-env site: two Strauss-scoped, two
`bin/scope.php`-scoped, with different versions; and an unscoped pair to prove the detection path.
It stays required in CI from Phase 1 onwards.
