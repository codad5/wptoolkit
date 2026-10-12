# ADR-0025 — The guard can autoload the plugin's own namespaces

- **Status:** Accepted
- **Date:** 2026-10-12
- **Deciders:** codad5
- **Affects:** `bootstrap/guard.php` (`namespaces` option), `bootstrap/namespaces.php` (new); completes ADR-0014

## Context

ADR-0014 made Composer optional and replaced 0.x's 654-line `Utils\Autoloader` with a ~50-line loader
for the library's own classes. It said consumers who want a loader for *their* code "get a documented
one-liner on top of the same loader". That one-liner was never built or documented: a plugin using the
standalone zip had to hand-write `spl_autoload_register()` for its own `src/`. 0.x did this for them
(`Autoloader::init(['MyPlugin\\' => __DIR__ . '/src/'])`), so it was also a regression for anyone
upgrading without Composer.

## Decision

The guard accepts `'namespaces' => array('MyPlugin\\' => __DIR__ . '/src')`.

- **PSR-4 only**: a prefix maps to one folder; several prefixes are allowed; the longest matching prefix
  is tried first. No class maps, no caching, no plugin tracking — the parts of the 0.x autoloader that
  ADR-0014 rightly dropped stay dropped.
- **Validated before anything loads**, in the guard's PHP 5.6 code: the option must be an array of
  non-empty string prefix => string folder, and each folder must exist. A missing folder pauses the
  plugin with an admin notice naming the namespace and folder; a malformed option pauses it with a
  notice for the developer. Neither is a fatal error (ADR-0013).
- **Registered after the environment checks pass**, by `bootstrap/namespaces.php`, which returns a
  closure and defines no global names (ADR-0005). It sits next to the guard and names no WPToolkit
  class, so scoping does not affect it and any copy's file will do.
- **A class name is turned into a path only if it is a valid class name.** `class_exists()` may be
  given arbitrary strings; `Prefix\..\secret` must not include a file outside the folder.
- It works in every `autoload` mode, including alongside Composer, where it is simply not needed.

## Options considered

- **Document a hand-written loader.** No code to maintain, but every standalone plugin carries thirty
  untested lines, and path mistakes surface as "class not found".
- **Put it in `bootstrap/autoload.php`.** That file is `require_once`d with no way to pass a map, and it
  is not loaded at all when Composer's autoloader is used.
- **Do it in the guard itself.** The guard must stay PHP 5.6 syntax and minimal; it validates and
  delegates.

## Consequences

- A standalone plugin needs no loader code of its own; the migration from 0.x's `Autoloader::init()` is
  one option.
- One more file in `bootstrap/`, shipped in both artefacts.
