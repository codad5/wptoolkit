# Track 0.x — Maintenance (parallel to all phases)

**Branch:** `0.x` · **PHP:** 8.0+ · **Policy:** security and data-loss fixes only.

---

## Rules

1. A fix lands on `0.x` with a regression test, then is forward-ported to `next` (or confirmed
   impossible there by design, and noted).
2. No features, no refactors, no dependency upgrades except for security.
3. Every fix is a patch release (`v0.2.x`) with a changelog entry.
4. Support ends **12 months after `v1.0.0`**; announced in the README and the release notes.

## Log

| Version | Date | What | Forward-ported |
| ------- | ---- | ---- | -------------- |
| v0.2.1  | —    | S1–S4, C2, C4, C5 (Phase 0) | by design in Phases 3–5 |
