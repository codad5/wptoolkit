# ADR-0012 — Zero runtime Composer dependencies, PSR via optional bridges

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `composer.json`, `src/Contracts`, `src/Adapters/*/Psr*Bridge`

## Context

Every runtime dependency we add is something each consumer must scope (ADR-0005). The `psr/*`
interfaces are a known trap in WordPress: one plugin ships `psr/log` 1.x, another 3.x (with
different method signatures), both unscoped; the first loaded wins and the other fatals. Depending
on PSR interfaces directly would put that trap in every consumer's path.

## Decision

`composer.json` `require` lists only `php`. Our contracts mirror PSR shapes (method names,
semantics), and each has an **optional** bridge adapter (`Psr3Bridge`, `Psr11Bridge`, `Psr16Bridge`)
that works only if the consumer installs the PSR package themselves (listed under `suggest`).

## Options considered

### Option A — Depend on `psr/log`, `psr/container`, `psr/simple-cache`

**Pros:** interoperability by type. **Cons:** the version-split trap above, in every consumer.
**Lost.**

### Option B — Our contracts + optional bridges (chosen)

**Pros:** nothing to scope or collide; interop still available. **Cons:** our interfaces aren't
type-compatible with PSR without the bridge.

## Consequences

**We accept:** a bridge class per PSR we support.

**We gain:** installing WPToolkit adds exactly one package to a plugin.

**This constrains:** CLAUDE.md rule 5; any runtime dependency needs a superseding ADR.

## Revisit when

WordPress itself ships PSR interfaces in core.
