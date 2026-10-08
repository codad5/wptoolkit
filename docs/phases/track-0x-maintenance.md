# Track 0.x — Frozen and deprecated

**Status:** frozen since 2026-10-07 (maintainer's call) · **PHP:** 8.0+ · **Successor:** 1.0

---

## Policy

1. **No releases, no tooling, no refactors.** All effort goes to 1.0.
2. **Exception — a hole becomes reachable on a live site.** Then resume the parked work, run its
   tests, and release a patch. The triggers are written down so nobody has to judge it from memory:

| Hole | Reachable when…                                                                                          | Parked fix |
| ---- | -------------------------------------------------------------------------------------------------------- | ---------- |
| S1 — Model search open to logged-out users | a site calls `enqueue_search_scripts()` (prints the nonce for every visitor)          | yes        |
| S2 — metabox data readable via Ajax        | a site lets users register, or has untrusted logged-in users (contact-form submissions live in meta) | yes |
| S3 — REST routes open by default           | a consumer adds a route returning private data without a `permission_callback`        | no — documented in member-directory, which already disabled `/users` and `/settings` |
| S4 — exception messages returned           | always, but only leaks internals when something throws                               | yes        |

3. **Every 1.0 phase that replaces a 0.x module re-proves these scenarios** against the new code
   (Phases 3 and 4 list them by name).
4. **Support ends** when the last known consumer (`member-directory`, `example-theme`,
   `another-theme`) runs 1.0.

## Parked work

Branch **`fix/0.2.1-security`** (pushed, unreleased), commit `d63e7c0`:

- S1 and S4 in `Model` search/autocomplete; S2 in `MetaBox::handle_ajax`.
- PHPUnit + Brain Monkey harness and regression tests.
- **The tests have never been run** (the dependency install didn't complete). Run them before any
  release.

Planned but not started (would have been `v0.3.0`, breaking): deny-by-default REST routes and Ajax
actions, real HTTP status codes with a matching JS client fix.

## Known consumers

| Project                   | How it loads 0.x                                  | Uses                                          |
| ------------------------- | ------------------------------------------------- | --------------------------------------------- |
| `member-directory` (plugin)            | Composer, `codad5/wptoolkit: dev-main` (unpinned) | Config, Settings, Page, Notification, Debugger, RestRoute, Model, MetaBox |
| `example-theme` (theme) | git clone in `wptoolkit/`                  | Ajax, EnqueueManager, Settings, Debugger, Model, MetaBox |
| `another-theme` (theme) | git clone in `wptoolkit/` (different git state) | same as acme-theme                            |
