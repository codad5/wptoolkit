# Build Status

> **Live progress.** Updated as work lands. The plan of record is [docs/phases/](docs/phases/README.md);
> this file is only "where are we right now". Step numbers match the phase docs.
>
> Last updated: **2026-10-07**

---

## Now: Phase 0 — Stabilize 0.x and lay the gates

| Step | What                                                         | State                         |
| ---- | ------------------------------------------------------------ | ----------------------------- |
| 0.1  | Repository & release hygiene (PR #4, tags, `0.x` branch)     | ⏳ waiting on maintainer: push/merge/tag |
| 0.2  | Security hotfix 0.2.1 (S1–S4)                                | ☐                             |
| 0.3  | Correctness hotfix (C2, C4, C5)                              | ☐                             |
| 0.4  | Tooling (PHPUnit, PHPStan, PHPCS, Composer scripts)          | ☐                             |
| 0.5  | CI on `0.x` and `next`                                       | ☐                             |
| 0.6  | Agent & docs scaffolding (CLAUDE.md, rules, ADRs, phases)    | ✅ 2026-10-07                 |

## Decided 2026-10-07

- [x] Strangler re-architecture, not a rewrite or a refactor ([ADR-0001](docs/adr/0001-strangler-re-architecture.md))
- [x] PHP 8.1 / WordPress 6.4 floor for 1.x; 0.x stays on PHP 8.0 ([ADR-0002](docs/adr/0002-php-8-1-and-wordpress-6-4-floor.md))
- [x] GPL-2.0-or-later ([ADR-0003](docs/adr/0003-gpl-2-0-or-later.md))
- [x] Two plugins may bundle two versions: scoping + consumer-prefixed globals + loud detection ([ADR-0005](docs/adr/0005-multiple-copies-coexist-via-scoping.md))
- [x] Library text domain `wptoolkit`; everything global prefixed by the consumer ([ADR-0006](docs/adr/0006-library-text-domain-and-consumer-prefixes.md))
- [x] Plugins boot inside a syntax-safe guard; wrong PHP/WP never crashes the site ([ADR-0013](docs/adr/0013-plugins-boot-through-a-syntax-safe-guard.md))
- [x] Composer is optional: a standalone zip with a tiny autoloader and a zero-tool scoper ([ADR-0014](docs/adr/0014-composer-is-optional.md))
- [x] Packaging is a WPToolkit dev tool, `wptoolkit package` ([ADR-0015](docs/adr/0015-packaging-is-a-toolkit-dev-tool.md), [Track P](docs/phases/track-p-packager.md))
- [x] The rebuild ships as `1.0.0`

## Waiting on the maintainer

- Push `next` to origin.
- Merge [PR #4](https://github.com/codad5/wptoolkit/pull/4), then tag `v0.1.0` / `v0.2.0` and cut `0.x` (step 0.1).
