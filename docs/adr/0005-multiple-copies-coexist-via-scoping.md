# ADR-0005 — Multiple copies coexist through scoping, prefixed globals and loud detection

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** [03-multi-version-coexistence.md](../architecture/03-multi-version-coexistence.md), `Foundation\Application`, `Foundation\Identity`, every module

## Context

WordPress has no dependency manager for plugins. Plugin A ships WPToolkit 1.2 in its `vendor/`;
plugin B ships 1.4. Both are active on one site. PHP can load a class name only once, so without a
design:

- whichever plugin's autoloader runs first wins, and the other plugin runs against a version it
  was never tested with — silently, until a method it needs doesn't exist (fatal on a live site);
- even with separate class names, both copies share **WordPress's** global namespaces: hook names,
  Ajax actions, REST namespaces, options, transients, tables, cron hooks, script handles, JS
  globals. A bare `wptoolkit_cache_*` transient written by 1.2 and read by 1.4 is a corruption bug.

0.x's per-plugin `Autoloader::init(..., $plugin_id)` maps namespaces per plugin but can't stop two
plugins resolving the same class name.

## Decision

Three layers, all required:

1. **Scoping is the supported install path.** Consumers prefix their copy with **Strauss**
   (preferred in WordPress) or PHP-Scoper, so plugin A's classes are `PluginA\Vendor\Codad5\WPToolkit\…`.
   We test this in CI with two scoped fixture plugins.
2. **Nothing global is shared.** Every name WordPress holds globally is derived from the
   **consumer's slug** via `Foundation\Identity` ([ADR-0006](0006-library-text-domain-and-consumer-prefixes.md)).
   Even two scoped copies therefore never collide at the WordPress level.
3. **Unscoped collisions fail loudly and safely.** Each copy records itself in a coexistence ledger
   at load. `Application::create()` accepts a `requires_toolkit` constraint; if the loaded
   WPToolkit doesn't satisfy it, the plugin **does not boot** and shows an admin notice naming both
   plugins and the fix — never a fatal error on the front end.

## Options considered

### Option A — Highest version wins (the Action Scheduler / CMB2 pattern)

Each copy registers its version; on `plugins_loaded` the newest loads. **Pros:** no build step for
consumers. **Cons:** only safe if every minor and major is fully backward compatible forever;
plugin A then runs against code it never tested. **Lost:** it moves the risk onto production sites.

### Option B — Major version in the namespace (`Codad5\WPToolkit\V1`)

**Pros:** 1.x and 2.x coexist. **Cons:** within a major, still Option A's problem; namespace churn
on every major. **Lost:** solves half the problem at a permanent cost.

### Option C — Scoping + prefixed globals + detection (chosen)

**Pros:** each plugin runs exactly the version it tested; WordPress-level collisions are impossible
by construction; mistakes are visible. **Cons:** consumers add a Strauss step to their build.

## Consequences

**We accept:** consumers must run Strauss (documented, with an example plugin and `make:plugin`
doing it for them); the coexistence ledger is our one sanctioned global.

**We gain:** independent plugin release cycles; no "works alone, fatals together" bugs.

**This constrains:** CLAUDE.md rules 2 and 3. No class names as strings in `src/` (Strauss can't
rewrite them reliably — use `::class`). No `wptoolkit_`-prefixed WordPress keys anywhere except the
ledger. CI must keep the two-plugin coexistence E2E green.

## Revisit when

WordPress core ships real plugin dependency management with versioned shared libraries.
