# ADR-0003 — The library is GPL-2.0-or-later

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `LICENSE`, `composer.json`, file headers, README

## Context

0.x contradicts itself: `composer.json` says MIT, every source file header says GPL-2.0-or-later.
A package with two licenses has, in practice, an unclear one — which blocks cautious adopters.
All commits are by the same author, so relicensing is the author's call alone.

## Decision

WPToolkit is licensed **GPL-2.0-or-later**, everywhere.

## Options considered

### Option A — MIT

**Pros:** maximal reuse, including outside WordPress. **Cons:** the code is WordPress-only (it calls
WordPress APIs throughout), so non-WordPress reuse is theoretical; it mismatches the headers already
in every file.

### Option B — GPL-2.0-or-later (chosen)

**Pros:** matches WordPress itself and the ecosystem norm (WordPress.org requires GPL-compatible, and
commercial WordPress plugins are typically GPL); matches the existing headers, so no file changes
meaning. **Cons:** some non-WordPress companies avoid GPL code — not our audience.

## Consequences

**We accept:** not being usable in proprietary non-WordPress projects.

**We gain:** one unambiguous license aligned with the platform.

**This constrains:** every new file carries the GPL-2.0-or-later header; dev tools may be any
license, but anything bundled in the dist must be GPL-compatible.

## Revisit when

A concrete adopter needs a non-GPL license for a reason we want to support — then consider dual
licensing, not a switch.
