# ADR-0002 — PHP 8.1 and WordPress 6.4 are the 1.x floor; 0.x stays on PHP 8.0

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `composer.json`, CI matrix, PHPCS `testVersion`, every PHP file

## Context

Commit `11db13b` deliberately made 0.x run on PHP 8.0. PHP 8.0 has been end-of-life since November
2023. The 1.0 design needs `readonly` properties (value objects, and the two `read-only` TODOs),
enums (statuses, HTTP methods), the `never` return type (`Response::send()`), and first-class
callable syntax — all PHP 8.1.

## Decision

1.x requires **PHP ≥ 8.1** and **WordPress ≥ 6.4**, tested up to the newest stable PHP and WordPress
nightly. 0.x keeps PHP ≥ 8.0 for its maintenance life.

## Options considered

### Option A — Keep 8.0

**Pros:** widest reach. **Cons:** no readonly/enums, so immutability is by convention only; we'd
design 1.0 around an EOL runtime. **Lost.**

### Option B — 8.1 (chosen)

**Pros:** every language feature the design needs; still the oldest version most hosts offer.
**Cons:** sites stuck on 8.0 stay on 0.x.

### Option C — 8.2

**Pros:** readonly classes, DNF types. **Cons:** cuts reach further for features we can live without.
**Lost:** nothing in the design requires 8.2.

## Consequences

**We accept:** 8.0 users need 0.x, which gets security fixes for 12 months after 1.0.0.

**We gain:** immutability the type system enforces.

**This constrains:** PHPCompatibilityWP `testVersion 8.1-`; CLAUDE.md rule 9.

## Revisit when

WordPress raises its own recommended minimum above 8.1, or PHP 8.1 usage among WordPress sites drops
below ~5% — then move to 8.2 in a minor release.
