# ADR-0001 — 1.0 is a strangler re-architecture, not a rewrite or a refactor

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** all of `src/`, [07-migration-from-0x.md](../architecture/07-migration-from-0x.md), every phase

## Context

The 0.x review (2026-10-07) found:

- The **public API style is good**: named constructors (`Config::plugin()`), fluent builders, a
  `Model`/`MetaBox` vocabulary consumers already use.
- The **foundation is the problem**: a static `Registry`, enforced singletons, a 2,655-line `Model`,
  and `Ajax` + `RestRoute` re-implementing the same security checks with different defaults (the
  root cause of S3).
- There are **zero tests**, so nothing proves behaviour is preserved by a change.
- There is **one known consumer** (`member-directory`) and no tagged release, so breaking changes
  are cheap now and expensive later.

## Decision

We build a new foundation in `src/` and port 0.x features into it module by module, each behind
tests, while 0.x lives in `legacy/` and keeps the example plugin working. A module's legacy class is
deleted in the same phase its replacement lands. The result ships as `1.0.0`.

## Options considered

### Option A — Refactor 0.x in place

**Pros:** no parallel code. **Cons:** with no tests, every refactor is unverified; the coupling
(static registry, singletons) runs through every file, so no single refactor is small.
**Lost:** unsafe without tests, and slow to reach a different architecture one file at a time.

### Option B — Big-bang rewrite

**Pros:** clean slate. **Cons:** nothing ships until everything ships; the hard-won edge cases in
0.x (quick edit, theme overrides, template fallbacks) get lost. **Lost:** the classic way rewrites fail.

### Option C — Strangler re-architecture (chosen)

**Pros:** each phase is shippable and testable; edge cases are captured by characterization tests
before porting; the DX consumers like is kept. **Cons:** two codebases in one repository for a few
phases.

## Consequences

**We accept:** `legacy/` and `src/` coexisting until Phase 5; a PHPStan baseline on `legacy/` only.

**We gain:** a green build at every step and a migration guide written from real ports.

**This constrains:** new code never imports from `legacy/`; `legacy/` may be shimmed onto `src/`
services, never the other way round.

## Revisit when

A phase's port needs more than ~50% new behaviour relative to its legacy class — that module is
then rewritten fresh, with a note in the migration map.
