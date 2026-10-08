# ADR-0013 — Every plugin boots through a syntax-safe guard and never takes the site down

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `bootstrap/guard.php`, the consumer's main plugin file, `make:plugin`, Phase 1 §1.7

## Context

The maintainer has hit this repeatedly: a plugin written for a newer PHP is uploaded to a site on an
older PHP, and **the whole site** returns a fatal error. wp-admin is gone too, so the plugin can't be
deactivated from the UI — only by renaming its folder over FTP or editing `active_plugins` in the
database.

Why it happens:

- PHP **parses a whole file before running any of it.** If the main plugin file (or anything it
  `require`s at top level) uses syntax the running PHP doesn't know, the version check *in that same
  file* never runs. A guard is only safe if its own file parses on every PHP that might run it.
- WordPress's `Requires PHP:` header (since 5.2) only blocks **activation from the admin UI**. It
  doesn't help if the plugin was already active and the host downgraded PHP, if files were uploaded
  over FTP, or if the header is wrong.
- Composer 2's `vendor/composer/platform_check.php` runs inside `vendor/autoload.php` and aborts the
  request when the PHP version doesn't match — another site-wide crash, triggered just by loading the
  autoloader.
- WordPress's fatal-error recovery mode (5.2+) emails the admin a link to a recovery session, but
  visitors still see "There has been a critical error".

## Decision

The consumer's plugin **lives inside a WPToolkit guard**:

```php
<?php
/**
 * Plugin Name: My Plugin
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */
// This file must parse on PHP 5.6: no types, no arrow fns, no `?->`, no attributes.
$guard = require __DIR__ . '/vendor/codad5/wptoolkit/bootstrap/guard.php';

$guard(__FILE__, array(
    'php'        => '8.1',
    'wp'         => '6.4',
    'extensions' => array('mbstring', 'json'),
), function () {
    // By now the checks passed and the guard has loaded classes — the plugin's own
    // vendor/autoload.php if Composer was used, else the toolkit's standalone loader (ADR-0014).
    require __DIR__ . '/src/boot.php';          // modern syntax lives from here on
});
```

`bootstrap/guard.php` is written in **PHP 5.6-compatible syntax**, defines **no global names** (it
`return`s a closure, so two plugins bundling two copies can't collide — ADR-0005), and:

1. **Checks before loading anything:** PHP version, WordPress version, required extensions, and
   (optionally) required plugins. On failure the plugin stays **active but inert**: nothing else is
   loaded, an admin notice names the plugin, what it needs, what the site has, and links to
   deactivate it; WP-CLI gets a warning. Activating it in this state is refused with a clear message.
2. **Contains boot failures:** the callback runs inside `try { … } catch (Throwable $e)`. Since
   PHP 7, a parse error in an *included* file throws a catchable `ParseError` — so even if a developer
   declares PHP 8.1 but uses 8.3 syntax, or Composer's platform check fires, the plugin goes inert
   with a notice and a log entry instead of taking the site down. (`catch (Throwable …)` still parses
   on PHP 5, because a catch type is only resolved when something is thrown.)
3. **Optionally contains runtime failures:** hooks registered through `HookRegistrar` can be wrapped
   so an exception in one plugin callback is logged and contained instead of crashing the page.
   Default: **on in production, off in development** (where you want the crash and the stack trace).

`make:plugin` (Phase 6) generates the main file in this shape. CI lints `bootstrap/guard.php` and the
example plugins' main files on the oldest PHP image we can run, plus PHPCompatibility with
`testVersion 5.6-` on exactly those files.

## Options considered

### Option A — Rely on the `Requires PHP` header

**Lost:** only protects admin-UI activation; doesn't cover the cases that actually hurt.

### Option B — A version check at the top of the main plugin file, written by each developer

**Pros:** no library involvement. **Cons:** every developer must remember to keep that file's syntax
old; nobody catches `ParseError` from the includes; it's re-invented, slightly wrong, per plugin.
**Lost:** this is exactly the kind of "remember to" a toolkit should remove.

### Option C — The library's guard; the app lives inside it (chosen)

**Pros:** one tested implementation; parse errors, platform-check failures and boot exceptions are
all contained; the developer writes nothing extra. **Cons:** the main plugin file has a fixed,
old-syntax shape — enforced by the scaffold and a lint.

## Consequences

**We accept:** one file in the library (and one per consumer) frozen to PHP 5.6 syntax; a catch-all
around boot that could hide a real bug — which is why it always logs, always shows an admin notice,
and rethrows when `WP_DEBUG` is on and the request is from a developer environment.

**We gain:** a plugin built on WPToolkit cannot white-screen a site by being installed on the wrong
PHP or WordPress version.

**This constrains:** CLAUDE.md rule 11. Nothing outside `bootstrap/guard.php` may be loaded before the
checks pass — including `vendor/autoload.php`. Fatal errors that PHP doesn't throw as exceptions
(out of memory, some compile errors such as class redeclaration) remain out of reach; ADR-0005's
scoping removes the most common of those.

## Revisit when

WordPress core starts refusing to *load* (not just activate) plugins whose `Requires PHP` isn't met.
