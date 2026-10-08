# Build Status

> **Live progress.** Updated as work lands. The plan of record is [docs/phases/](docs/phases/README.md);
> this file is only "where are we right now". Step numbers match the phase docs.
>
> Last updated: **2026-10-08**

---

## Now: Phase 7 — Release (the steps left need the maintainer)

| Phase | State |
| ----- | ----- |
| 0 — Gates | ✅ CI green on PHP 8.1–8.4 |
| 1 — Foundation & coexistence | ✅ DoD demonstrated: 9/9 E2E on real WordPress (run 37643432573), 118 unit tests |
| 2 — Infrastructure adapters | ✅ cache, log, HTTP client, rate limit, filesystem, clock, PSR bridges; integration on WP 6.4/latest |
| P — Build tool (`Build::plugin()->…`) | ✅ library + `wptoolkit-build` (package, verify, doctor, init), `Build::library()`, reusable `package-wordpress.yml`; ☐ migrating the 3 projects (maintainer's go-ahead) |
| 3 — HTTP layer | ✅ router + one pipeline for REST/Ajax, deny-by-default, validation, 429s, JS client |
| 4 — Data layer | ✅ fields, MetaBox (quick edit without a public endpoint), entities + 4 repository adapters on one contract, Query, migrations (batched, locked, reversible), Search (C1/S1 fixed), admin columns, 0.x data shapes pinned; 0.x `Model`/`MetaBox` deleted |
| 5 — Presentation and admin | ✅ views (theme overrides, escaper), assets (locked `window.wptoolkit`, RTL), settings (sensitive by name, encryption), admin pages, notices, public front-end pages; `legacy/` deleted. DoD on real WordPress: the todo example in English and Arabic, by keyboard, zero notices |
| 6 — DX, docs, AI | ✅ `wp {slug}` commands, README, guides, migration guide from member-directory/acme-theme, `llms.txt`; ☐ timed getting-started run and two-agent trial (maintainer); 1.1: phpDocumentor, AGENTS.md template, Abilities |
| 7 — Release | ✅ `release.yml` (gates → version check → standalone zip by `wptoolkit-build` → notes → GitHub Release), security checklist; ☐ tag last 0.x, pin member-directory, RC + member-directory on staging + soak, PR → `main`, `v1.0.0` (maintainer) |

Phase 1 delivered: kernel (container, providers, lifecycle, hook containment), `Identity`,
library translations, coexistence ledger, `requires_toolkit` with activation refusal naming the
plugin that won, `bootstrap/guard.php` (PHP 5.6-safe), standalone autoloader, `bin/scope.php`.

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
