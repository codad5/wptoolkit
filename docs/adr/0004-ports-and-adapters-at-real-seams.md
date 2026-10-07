# ADR-0004 — Ports and adapters, only at real seams

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `src/Contracts`, `src/Adapters`, [02-system-architecture.md](../architecture/02-system-architecture.md)

## Context

0.x calls WordPress (and `$wpdb`, `wp_remote_request`, transients) directly from domain classes.
That makes it untestable without a full WordPress install, and makes swapping a backend (Redis
object cache vs transients, CPT vs custom table) an edit to core.

The opposite failure is common too: an interface for every class, where most interfaces have one
implementation forever and exist only as ceremony.

## Decision

Domain code depends on **contracts** in `src/Contracts`; implementations live in `src/Adapters`.
A contract exists **only** where there are at least two real implementations, **or** one real
implementation plus a test double that tests genuinely need. Adapters are chosen by **factories**
from configuration, so adding a backend never edits core.

The contracts at 1.0: `CacheStore`, `Logger`, `HttpClient`, `RateLimiterStore`, `Filesystem`,
`Clock`, `Translator`, `Repository`, `Renderer`, `Container`, `Middleware`.

## Options considered

### Option A — Call WordPress directly (0.x)

**Lost:** untestable, unswappable.

### Option B — Interface per class

**Lost:** doubles the file count for no flexibility anyone uses.

### Option C — Contracts at real seams (chosen)

**Pros:** the flexibility exists exactly where backends really vary, and tests can run without
WordPress. **Cons:** a judgement call per seam — hence the two-implementation rule.

## Consequences

**We accept:** a thin wrapper layer over WordPress functions in adapters.

**We gain:** in-memory adapters make unit tests fast; consumers plug in backends (e.g. their own
Guzzle `HttpClient`) without forking.

**This constrains:** CLAUDE.md rule 4. Domain code under `src/Http`, `src/Data`, `src/View` never
calls `get_transient`, `wp_remote_*`, `$wpdb` or `error_log` directly — enforced by a PHPStan rule
in Phase 1.

## Revisit when

A contract still has only one implementation and no test double a year after 1.0 — fold it back.
