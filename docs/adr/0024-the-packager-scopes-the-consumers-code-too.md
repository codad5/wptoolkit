# ADR-0024 — The packager scopes the consumer's code and autoloader, not only the library

- **Status:** Accepted
- **Date:** 2026-10-11
- **Deciders:** codad5
- **Affects:** `packages/build` (`Steps\ScopeToolkit`, `Steps\Verify`, `NamespaceRewriter`), `Cli\Scaffolder`, `resources/stubs`

## Context

Scoping (ADR-0005) renames a bundled copy from `Codad5\WPToolkit` to the consumer's namespace. Three
things have to agree on that name: the library's files, the consumer's code that uses it, and — for a
Composer install — the generated autoload map.

`Build::scope()` only ran the library's `bin/scope.php`, which rewrites the library's own `src/` and
`bootstrap/`. For a plugin developed with `composer require codad5/wptoolkit`, the documented
`->composer()->scope()->zip()` therefore produced a zip whose code asked for classes that no longer
existed under that name: a fatal error on activation. Verification did not catch it. The phase
document claimed "Strauss for Composer", which was never implemented. A consumer migrating to 1.0
found this by reading the code, before shipping.

Separately, the scaffolding stubs named `Codad5\WPToolkit` in plain text, so `wp {slug} make:*` in a
plugin with a scoped copy wrote files that could not load.

## Decision

1. `scope()` rewrites, in the **staged copy only**: the library (as before), then every PHP file of the
   consumer's own — not the library copy, not other packages under `vendor/`, not `node_modules` — using
   the same token-level rule as `bin/scope.php` (qualified names only; strings and comments untouched).
   The packager's namespace (`Codad5\WPToolkit\Build`) is never renamed, because scoping does not
   rename the packager.
2. For a copy under `vendor/`, the package's PSR-4 prefix is renamed in `vendor/composer/installed.json`
   and `composer dump-autoload --optimize` is run. Composer rebuilds its maps from the scoped files; we
   do not patch generated autoload files.
3. The step then loads the scoped `Application` through `vendor/autoload.php` in a separate process
   and fails the build if it does not load.
4. `Verify` refuses any package in which the bundled copy is scoped but the consumer's code still names
   the unscoped library — whether or not `scope()` was used.
5. A class name in a **string** cannot be renamed safely; the step warns and names the file.
6. `composer()` after `scope()` is refused: reinstalling would restore the unscoped library.
7. Stubs use a `{{toolkit}}` placeholder, filled from the running copy's own namespace
   (`__NAMESPACE__`, which scoping renames), so scaffolded files load from any copy.

## Options considered

- **Strauss** (what the phase document claimed). Mature, and also scopes third-party dependencies. But
  it is a dev dependency and configuration every consumer must add, and the documented one-line flow
  would depend on another project's behaviour. It stays documented as the route for consumers whose
  other dependencies also require WPToolkit.
- **Refuse to scope a Composer install** and require consumers to write against the scoped namespace.
  Smallest change, cannot ship a broken zip, but removes a documented feature and is awkward with
  Composer, where the installed package is unscoped.
- **Patch `autoload_static.php` and friends directly.** They embed prefix lengths and an optimized
  classmap; regenerating is more robust than editing them.
- **Have `bin/scope.php` rewrite the stubs.** Stubs are not valid PHP (placeholders), and it would only
  help copies scoped by a new enough script; the runtime namespace is always right.

## Consequences

- The packager now edits consumer code in staging. It is token-based and limited to qualified names
  that start with the library's namespace.
- A dependency in `vendor/` that itself uses WPToolkit is not scoped by this step.
- Consumers develop against the unscoped copy and ship a scoped one, so the built zip is what must be
  tested; the end-to-end suite does this for the example plugin.
- `NamespaceRewriter` duplicates about fifteen lines of `bin/scope.php`'s rule. `bin/scope.php` must stay
  a dependency-free script for standalone users (ADR-0014), so the duplication is accepted.
