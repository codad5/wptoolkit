# ADR-0014 — Composer is optional: a standalone build with a tiny autoloader and a zero-tool scoper

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `bootstrap/autoload.php`, `bin/scope.php`, `release.yml`, [03-multi-version-coexistence.md](../architecture/03-multi-version-coexistence.md), ADR-0005, ADR-0013

## Context

Many WordPress developers don't use Composer — they download a zip and `require` a file. 0.x serves
them with a custom `Autoloader` (654 lines). The 1.0 plan's first draft said "Composer + Strauss
replace it", which would quietly lock those developers out. The project's intent is the opposite:
**don't force a toolchain on the people using the library.**

Two things Composer users get must still reach non-Composer users:

1. **Class loading.**
2. **Scoping** (ADR-0005) — Strauss and PHP-Scoper are Composer-based tools.

## Decision

Every release ships two artefacts with **identical code**:

| Artefact                     | For                | Loading                                  | Scoping                                   |
| ---------------------------- | ------------------ | ---------------------------------------- | ----------------------------------------- |
| Composer package (Packagist) | Composer users     | Composer's autoloader                    | Strauss / PHP-Scoper                      |
| Standalone zip (GitHub Release) | Everyone else   | `bootstrap/autoload.php` (~50-line PSR-4 loader, no globals) | `php bin/scope.php MyPlugin` — rewrites `Codad5\WPToolkit` → `MyPlugin\WPToolkit` in place |

`bin/scope.php` needs only the PHP CLI — no Composer, no Node. It works with a token-based namespace
rewrite, which is reliable **because we keep our own code rewrite-friendly**: no class names in
strings, no dynamic namespace construction (already required by ADR-0005).

**The guard picks the loader, so the developer doesn't have to.** Its `autoload` option defaults
to `'auto'`:

1. If the plugin has `vendor/autoload.php` → Composer was used: require it, and **don't** register
   the standalone loader (Composer already maps the toolkit, scoped or not).
2. Otherwise → require the toolkit's own `bootstrap/autoload.php`.
3. `'composer'`, `'standalone'` or a file path force one choice; `false` means "I load classes myself".

`bootstrap/autoload.php` is also **idempotent per copy**: it remembers the directory it registered
and does nothing on a second include, so requiring it alongside Composer is harmless rather than
a double registration.

The same main plugin file therefore works for both install paths:

```php
$guard = require __DIR__ . '/lib/wptoolkit/bootstrap/guard.php';   // or vendor/codad5/wptoolkit/…

$guard(__FILE__, array('php' => '8.1', 'wp' => '6.4'), function () {
    require __DIR__ . '/src/boot.php';   // classes are already loadable: Composer's or ours
});
```

A developer who scopes nothing at all — Composer or not — is still protected by ADR-0005's loud
detection and ADR-0013's guard: a version conflict makes their plugin inert with a notice, never a
crash.

## Options considered

### Option A — Composer only

**Lost:** contradicts the project's aim; excludes a large share of WordPress developers.

### Option B — Keep 0.x's 654-line general-purpose autoloader

It also autoloads the consumer's own classes, tracks plugins, manages class maps. **Lost:** most of
that is an application concern; the size is a maintenance cost and a test burden for a feature a
50-line PSR-4 loader covers. Consumers who want an autoloader for their own code get a documented
one-liner on top of the same loader.

### Option C — Two artefacts, same code (chosen)

**Pros:** no forced toolchain; scoping available to everyone. **Cons:** two build outputs and a
scoper of our own to test.

## Consequences

**We accept:** maintaining `bin/scope.php` and testing it in CI (scope the standalone build, run the
coexistence E2E against two scoped standalone copies as well as two Strauss-scoped Composer copies).

**We gain:** WPToolkit works for traditional PHP developers without making them adopt Composer.

**This constrains:** `bootstrap/autoload.php` defines no global functions (it `return`s or uses a
closure with `spl_autoload_register`); every doc and example shows both install paths;
`make:plugin` asks which one.

## Revisit when

The standalone zip's download share drops below ~5% of installs for a year.
