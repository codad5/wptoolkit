# ADR-0008 — Deny by default; one middleware pipeline for Ajax and REST

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `src/Http`, [06-security.md](../architecture/06-security.md)

## Context

In 0.x, `RestRoute` defaults `permission_callback` to `__return_true` (S3), `Model` registers public
`nopriv` search endpoints without capability checks (S1), and `MetaBox` exposes any post's meta to
logged-out users behind only a nonce (S2). `Ajax` and `RestRoute` each implement nonces, capabilities
and rate limits separately, with different defaults — the same rule written twice drifts.

## Decision

- A route **must** declare its access rule: `->public()`, `->loggedIn()`, `->can($capability)` or
  `->authorize($policy)`. A route without one throws at registration in development and is denied
  in production.
- Ajax and REST are **transports** over one `Router` and one middleware pipeline. A controller is
  written once and exposed through either or both.
- Errors map to real HTTP statuses; clients get a generic, translated message; details go to the log.

## Options considered

### Option A — Keep two systems, fix their defaults

**Lost:** the duplication that produced the drift remains.

### Option B — REST only

**Pros:** one transport. **Cons:** many plugins still need admin-ajax (older themes, some hosts
block the REST API for anonymous users). **Lost:** too restrictive for a toolkit.

### Option C — One pipeline, two transports, deny by default (chosen)

## Consequences

**We accept:** a prototype route needs one extra call (`->public()`).

**We gain:** a security rule written once applies everywhere; one place to audit.

**This constrains:** CLAUDE.md rule 1. Phase 0's S1–S4 regression tests are rewritten against this
layer in Phase 3 and must stay green.

## Revisit when

Never for "deny by default". The transports are revisited if WordPress deprecates admin-ajax.
