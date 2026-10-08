# ADR-0007 — Our own small container; no static registry, no enforced singletons

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `Foundation\Container`, `Foundation\Application`, `Foundation\ServiceProvider`

## Context

0.x wires services through `Registry` (static arrays keyed by app slug) and makes every `Model` an
enforced singleton. Static state is shared across every plugin that loads the same class (see
ADR-0005), can't be reset between tests, and hides dependencies — a class can `Registry::get()`
anything from anywhere.

## Decision

Each consuming plugin creates one `Application`, which owns a `Container` (bind, singleton, factory,
constructor autowiring). Services receive dependencies through constructors. Features are wired in
`ServiceProvider`s (`register()` binds, `boot()` hooks in). The container's interface mirrors PSR-11
(`get`, `has`), and a PSR-11 bridge is available when the consumer installs `psr/container` itself
([ADR-0012](0012-zero-runtime-dependencies.md)).

## Options considered

### Option A — Keep `Registry` (0.x)

**Lost:** global state; service locator everywhere.

### Option B — `league/container` or `php-di`

**Pros:** mature. **Cons:** a runtime dependency every consumer must scope, plus the `psr/container`
version split across plugins. **Lost:** ~300 lines of our own code is cheaper than that.

### Option C — Own container (chosen)

**Pros:** zero dependencies, exactly the features we need, fully tested. **Cons:** we maintain it.

## Consequences

**We accept:** maintaining a container; no compiled container in 1.0 (reflection cost is measured
in Phase 7).

**We gain:** per-plugin isolation and tests that build a fresh application each time.

**This constrains:** CLAUDE.md rule 2. Calling `$container->get()` from domain code is a service
locator and is rejected in review — only providers and the kernel resolve from the container.

## Revisit when

Boot cost from reflection exceeds the Phase 7 budget — then add a cached/compiled resolution map.
