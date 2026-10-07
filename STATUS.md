# Build Status

> **Live progress.** Updated as work lands. The plan of record is [docs/phases/](docs/phases/README.md);
> this file is only "where are we right now". Step numbers match the phase docs.
>
> Last updated: **2026-10-07**

---

## Now: Phase 1 — Foundation and the coexistence proof

Phase 0 is done: CI is green on `next` (run 37632478575).

Phase 0 (lay the gates on `next`) runs alongside: tooling first, so Phase 1 code lands with tests.

| Step | What                                                         | State |
| ---- | ------------------------------------------------------------ | ----- |
| 0.1  | Repository hygiene on `next`                                 | ✅    |
| 0.2  | Tooling (PHPUnit, Brain Monkey, PHPStan, PHPCS)              | ✅ (verified in CI; local install pending) |
| 0.3  | CI                                                           | ✅ green on PHP 8.1–8.4 |
| 0.4  | Agent and docs scaffolding                                   | ✅ 2026-10-07 |
| 1.1  | Strangler layout (`legacy/`, new `src/` namespaces)          | ✅    |
| 1.1b | Usage inventory of pau, silverbird, nile                     | ✅ inventory · ☐ data fixtures |
| 1.2  | Kernel (container, providers, hooks, config, lifecycle)      | ✅ except activation/deactivation/uninstall wiring |

## Decided 2026-10-07

- [x] Strangler re-architecture, not a rewrite or a refactor ([ADR-0001](docs/adr/0001-strangler-re-architecture.md))
- [x] PHP 8.1 / WordPress 6.4 floor for 1.x; 0.x stays on PHP 8.0 ([ADR-0002](docs/adr/0002-php-8-1-and-wordpress-6-4-floor.md))
- [x] GPL-2.0-or-later ([ADR-0003](docs/adr/0003-gpl-2-0-or-later.md))
- [x] Two plugins may bundle two versions: scoping + consumer-prefixed globals + loud detection ([ADR-0005](docs/adr/0005-multiple-copies-coexist-via-scoping.md))
- [x] Library text domain `wptoolkit`; everything global prefixed by the consumer ([ADR-0006](docs/adr/0006-library-text-domain-and-consumer-prefixes.md))
- [x] Plugins boot inside a syntax-safe guard; wrong PHP/WP never crashes the site ([ADR-0013](docs/adr/0013-plugins-boot-through-a-syntax-safe-guard.md))
- [x] Composer is optional: a standalone zip with a tiny autoloader and a zero-tool scoper ([ADR-0014](docs/adr/0014-composer-is-optional.md))
- [x] Packaging is a WPToolkit dev tool, `wptoolkit package` ([ADR-0015](docs/adr/0015-packaging-is-a-toolkit-dev-tool.md), [Track P](docs/phases/track-p-packager.md))
- [x] 0.x is frozen and deprecated; parked fixes on `fix/0.2.1-security` ([track](docs/phases/track-0x-maintenance.md))
- [x] 1.0 reads 0.x data unchanged; only the PHP API breaks ([ADR-0016](docs/adr/0016-1-0-reads-0x-data-unchanged.md))
- [x] The rebuild ships as `1.0.0`

## Waiting on the maintainer

- Run the dev-tool install on `next`: `composer update -vvv` (verbose, so a stall is visible).
- Optional now that 0.x is frozen: merge [PR #4](https://github.com/codad5/wptoolkit/pull/4) and tag `v0.2.0` as the last 0.x.
