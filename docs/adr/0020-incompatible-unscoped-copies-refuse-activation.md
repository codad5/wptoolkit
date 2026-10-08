# ADR-0020 — The loaded copy wins; an incompatible plugin is refused at activation, never crashed

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `Foundation\Application`, `Foundation\ToolkitCompatibility`, `Foundation\CopyOwner`,
  [03-multi-version-coexistence.md](../architecture/03-multi-version-coexistence.md), ADR-0005

## Context

ADR-0005 makes scoping the supported path, so two plugins normally run two independent copies.
For **unscoped** copies PHP can load a class once, so the first copy loaded serves every plugin.
The maintainer proposed: each version knows which older versions it stays compatible with; when a
plugin needs a version the loaded copy can't satisfy, refuse to activate it instead of activating
it and breaking later; and keep a site-wide record of the loaded version.

## Decision

1. **Compatibility is SemVer** (ADR-0011): a consumer declares `requires_toolkit`, e.g. `'^1.2'`
   (1.2 or any later 1.x). `Application::VERSION` is checked against it with
   `Support\VersionConstraint`.
2. **The loaded copy always wins** — that is PHP's behaviour for unscoped classes and we don't fight
   it. WordPress loads active plugins in a fixed alphabetical order, so activation order can't
   change which copy wins, and the library never suggests "activate this one first".
3. **On an incompatible copy the consumer's application stays inert** — no provider is
   registered or booted — and:
   - **during activation** it calls `wp_die()` from its activation hook, so WordPress never
     records the plugin as active;
   - **otherwise** (already active, e.g. the other plugin was updated) it shows an admin notice to
     users who can `activate_plugins`.
4. **The message names the plugin, must-use plugin or theme whose copy won** (resolved from the
   copy's path by `Foundation\CopyOwner`), shows the path, and lists what to do: deactivate the
   other one; install a version of this plugin built for the loaded major, or update the other;
   for developers, ship a scoped copy.
5. **No stored "site-wide loaded version" option.** It would go stale (FTP updates, changed load
   order, a plugin deleted without deactivation). The in-memory coexistence ledger is recomputed on
   every request; during activation every active plugin has already loaded, so it knows exactly
   which copy won.
6. Messages are translated only at render time (admin notice, activation request), never while
   booting, so WordPress 6.7's early-translation notice can't fire.

### The check runs in the consumer's own guard, not in the loaded classes

Raised by the maintainer the same day: with unscoped copies, `Application` is *the other copy's*
class, so a check written inside it runs the other copy's code — possibly a 0.x with no check, or a
future version with different rules. A check can't trust the classes it's checking.

So the authoritative check lives in `bootstrap/guard.php`, which every plugin loads **by file
path** and which returns a closure — a file path can't be taken by another copy the way a class
name can. The guard reads its own copy's namespace and version from its own files without loading
a class, uses reflection to see whether `Application` was already loaded **from a different file**,
reads that copy's version, and matches the consumer's `toolkit` constraint with a version matcher
built into the guard itself. On a mismatch it never touches a WPToolkit class: its own activation
closure refuses activation, and its own notice explains.

`ToolkitCompatibility` inside `Application` remains as a second layer for consumers who don't use
the guard — with the documented limit that it is then the loaded copy's code doing the judging.

## Options considered

### Option A — Activate, then go inert (first draft)

**Lost:** the administrator sees "activated" and only later a notice; refusing up front is clearer.

### Option B — Persist the loaded version in an option and check it on activation

**Lost:** goes stale; the ledger is always current.

### Option C — Ledger + refuse at activation + inert with notice otherwise (chosen)

## Consequences

**We accept:** a plugin already active can't be "un-activated" retroactively; it stays inert with a
notice until the conflict is resolved.

**We gain:** no white screens from version conflicts, and messages that tell the administrator
exactly which other plugin is involved and what to do.

**This constrains:** every consumer should set `requires_toolkit`; `make:plugin` (Phase 6) writes it.

## Revisit when

WordPress gains dependency resolution between plugins that covers shared libraries.
