# ADR-0018 — Data changes go through versioned migrations, using expand → migrate → contract

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `Data\Migrations`, settings, meta, custom tables, CLI, [ADR-0016](0016-1-0-reads-0x-data-unchanged.md)

## Context

ADR-0016 keeps 0.x's stored formats so upgrading to 1.0 needs no data change. But consumers'
data will still need to evolve: rename a meta key, split a setting, move a high-volume post type to
a custom table, change a serialized shape. Today every consumer does this by hand (silverbird's
`Contact_Form` already rewrites keys in its own code), with no record of what ran, no locking, and
no way to resume a half-finished run on a large site.

The maintainer asked whether a "data version" should let the code pick between the old format and
the latest one.

## Decision

1. **One current format, never two read paths.** Code reads and writes only the current format.
   Old data reaches it through migrations, not through version branches scattered in domain code.
2. **Migrations are versioned classes per consumer app**, ordered by id
   (`2026_10_07_120000_rename_movie_rating`), each with `up()` and, where possible, `down()`.
   Applied ids are recorded in the consumer's option `{slug}_migrations` (a list, not a single
   number, so migrations from parallel branches don't collide).
3. **Expand → migrate → contract** for anything a live site reads:
   - *expand*: write both old and new (or teach the reader the new format) — release N;
   - *migrate*: copy existing data to the new format — a migration in release N;
   - *contract*: stop writing the old format and delete it — a later migration in release N+1,
     once nothing reads it. Downgrading to release N stays possible until the contract step.
4. **Large data migrates in batches**: a migration can declare itself batched; the runner processes
   a fixed number of rows per run via WP-Cron, records progress, and resumes after a timeout.
5. **Runs are safe**: a lock (an option with an expiry) prevents two concurrent runs; every
   migration is idempotent; failures stop the run, are logged, and show an admin notice.
6. **When they run**: on plugin activation, and on `admin_init` when the code's latest migration id
   isn't recorded yet (cheap check against one option). Also `wp {slug} migrate`, `migrate:status`,
   `migrate:rollback`, all with `--dry-run`.
7. **Settings get schema versions too**: a settings group may declare *upcasters* that turn an
   old stored shape into the current one on read, for cases where an eager migration isn't worth
   it (a handful of options). Writes always store the current shape.
8. **The library's own data**: WPToolkit stores almost nothing durable (caches and transients are
   disposable, notices are per user). If it ever does, it uses the same mechanism under its own
   `wptoolkit_` ledger entry.

## Options considered

### Option A — A data-version flag that switches between old and new code paths

**Pros:** no data rewrite. **Cons:** every reader branches on the version forever; tests double;
the old path never dies. **Lost.**

### Option B — Migrate everything automatically on upgrade, in one go

**Lost:** times out on large sites, can't resume, can't downgrade.

### Option C — Versioned migrations with expand/contract, batching and upcasters (chosen)

**Pros:** one code path; resumable; downgradable until the contract step; visible and auditable.
**Cons:** two releases for a breaking data change; a runner to maintain.

## Consequences

**We accept:** a migration runner, lock and batch processor in Phase 4; consumers write migration
classes instead of ad-hoc code.

**We gain:** data can evolve safely after 1.0, including opting into new key schemes (ADR-0016).

**This constrains:** no domain code branches on a data version; a stored-format change ships as a
migration plus an ADR when it affects the library itself.

## Revisit when

A consumer needs cross-site (multisite network-wide) migrations — then add network scope.
