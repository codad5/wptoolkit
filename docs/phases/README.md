# Build Phases — WPToolkit 1.0

## How to read this plan

Phases are ordered by **dependency and risk**, not by what's fun to build.

> **The order rule: prove the riskiest load-bearing thing early, behind the smallest slice that
> shows it works.** For a WordPress library that is *two plugins bundling two different versions of
> us on one site*. If that design is wrong, every later phase is built on sand — so Phase 1 proves
> it before any feature is ported.

Each phase ends in something **demonstrable**. Its Definition of Done is an acceptance bar: a phase
is done when someone can perform the listed actions and see the listed outcomes — not when the code
is written.

---

## The phases

| #   | Phase                                                                     | Effort | Unblocks                          |
| --- | ------------------------------------------------------------------------- | ------ | --------------------------------- |
| 0   | [Lay the gates](phase-0-stabilize-and-gates.md)                           | S      | Everything                        |
| 1   | [Foundation and the coexistence proof](phase-1-foundation-and-coexistence.md) | M  | Every module                      |
| 2   | [Infrastructure adapters](phase-2-infrastructure-adapters.md)             | M      | HTTP, data, logging               |
| 3   | [HTTP layer: one pipeline for Ajax and REST](phase-3-http-layer.md)       | M      | Data endpoints, admin             |
| 4   | [Data layer: fields, entities, repositories](phase-4-data-layer.md)       | L      | The product's spine               |
| 5   | [Presentation and admin](phase-5-presentation-and-admin.md)               | M      | Deleting `legacy/`                |
| 6   | [Developer experience, docs and AI-readiness](phase-6-dx-docs-ai.md)      | M      | Adoption                          |
| 7   | [Release 1.0](phase-7-release.md)                                         | S      | 1.0.0                             |
| 0.x | [Frozen and deprecated](track-0x-maintenance.md) — fixes only if a hole becomes reachable | — | Nothing |
| P   | [Packager](track-p-packager.md) — **parallel, can start after Phase 0**   | S–M    | Correct zips for every project now |

```
Phase 0 ─▶ Phase 1 ─┬─▶ Phase 2 ─┬─▶ Phase 3 ─┐
                    │            └─▶ Phase 4 ─┴─▶ Phase 5 ─▶ Phase 6 ─▶ Phase 7
                    └──────────────────────────────────────────────────────────▶ (0.x track runs alongside)
```

Phases 3 and 4 can overlap once Phase 2's cache and logger contracts exist.

---

## Timeline — measured, not guessed

The estimate is calibrated against a real project with the same author and the same AI-assisted
workflow: an earlier **reference project**, measured from its git history on 2026-10-07.

| Reference project, first commit → last commit      | Value                                    |
| -------------------------------------------------- | ---------------------------------------- |
| Calendar span                                      | 2026-09-23 → 2026-10-03 = **11 days**    |
| Active days (days with commits)                    | **7** (64% of calendar days)             |
| Commits                                            | **248** (~35 per active day, peak 87)    |
| Code (TS / Rust / SQL / JS), incl. tests           | ~94k lines, of which ~25k tests          |
| Markdown (architecture, 71 ADRs, phases, runbooks) | ~30k lines                               |
| Total throughput                                   | ~124k lines ≈ **~18k lines per active day** |

**WPToolkit 1.0's projected size:** source ~11–13k (smaller than 0.x's 17k: the autoloader,
registry and debugger go away and Ajax/REST stop duplicating), tests ~9–11k, docs ~8k, CI and config
~1k → **~30k lines, about a quarter of the reference project.**

Raw scaling says ~2 active days. That is wrong, because this work has costs the reference project did not:

| Adjustment                                                                     | Cost                |
| ------------------------------------------------------------------------------ | ------------------- |
| Porting tax: read each 0.x class, pin its behaviour with characterization tests | ×1.5 on code phases |
| WordPress test infrastructure (wp-env, wp-phpunit, MySQL) and a PHP × WP CI matrix with slow feedback | +1–1.5 days |
| Coexistence harness: a scoping build and a two-plugin end-to-end site          | +1 day              |
| RTL locale + translation end-to-end                                            | +0.5 day            |
| Your review and decision time (the reference project's bottleneck too)                       | included in the 64% active ratio |

| Phase | Active days (the reference project pace) |
| ----- | -------------------------- |
| 0     | 0.5                        |
| 1     | 1.5                        |
| 2     | 0.75                       |
| 3     | 1                          |
| 4     | 1.5–2                      |
| 5     | 1                          |
| 6     | 0.75                       |
| 7     | 0.5 + dogfood wait         |
| **Total** | **~7.5–8 active days → ~2–3 calendar weeks** at the reference project's 64% active ratio |

### Measured against reality (2026-10-07, after Phases 0–3 and most of Track P)

| | Estimate above | Actual (git) |
| --- | --- | --- |
| Phases 0–3 + Track P core | ~4¼ active days | **~5.3 hours** (14:16 → 19:34, 46 commits, ~17.8k lines incl. tests and docs) |

The estimate was ~6× pessimistic. Lines of code proved a weak proxy (docs written up front made
later phases fast; coexistence work cost far more thought per line than adapters), and "active day"
was undefined. What actually paced the work: CI round-trips (~10 min per push) and design decisions.
**Revised remaining estimate: ~6–8 hours of build work** (Phase 4 ~2–3 h, Phase 5 ~1.5–2 h,
Phase 6 ~1–1.5 h, Phase 7 + Track P ~1 h), **plus the human-paced steps** that don't compress:
review and merge, pinning member-directory, migrating member-directory on staging and its soak period.

**Caveats, stated plainly.** The reference project's rate measures output, not quality, and it was greenfield;
porting is slower per line. Phase 7 includes migrating `member-directory`, which runs at the speed
of whoever owns that site. Without AI assistance, the same plan is ~12–15 weeks part-time — the order
does not change, only the calendar.

---

## Phase discipline

1. **One phase at a time.** A task that isn't in the current phase is a conversation, not a quiet
   addition.
2. **A phase is done when its DoD is demonstrated**, not when the code is written.
3. **Every phase leaves CI green**, including every earlier phase's acceptance tests.
4. **Docs change with the code.** New decision → ADR. Changed structure → architecture doc. Same PR.
5. **Deferred work is written down** in the phase's "Deliberately not in this phase" section.

## Tracking

Each phase doc has a numbered task checklist. Tick items as they land and add what was missed. The
checklist in the repository is the plan of record; [STATUS.md](../../STATUS.md) summarizes it.
