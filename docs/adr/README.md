# Architecture Decision Records

An ADR captures **one decision**, the context that forced it, the options considered, and the
consequences we accepted. It is written once and then left alone: if the decision changes, a new ADR
supersedes it. We never edit history — the reasoning that was true when it was made is what a future
reader needs.

## Why we bother

Months from now someone will look at, say, the rule that every option key is prefixed by the
consumer's slug and think "this is ceremony, let me simplify it." The ADR is what tells them it
exists because two plugins on one site bundle two versions of this library.

## When to write one

Write an ADR when a decision:

- is hard or expensive to reverse (a PHP floor, a license, a public API shape);
- constrains how other code must be written (a layering rule, a naming rule);
- rejects an option a reasonable engineer would otherwise reach for;
- costs something obvious in exchange for something less obvious.

Not for a naming choice, a library with no real alternative, or anything you wouldn't defend in review.

## Process

1. Copy [`0000-template.md`](0000-template.md), number it sequentially.
2. `Proposed` while under discussion; `Accepted` once merged.
3. Link it from the architecture doc it affects, and from `CLAUDE.md` if it creates a rule.
4. To change a decision: new ADR with `Supersedes: ADR-XXXX`; edit the old one only to add
   `Superseded by: ADR-YYYY`.

## Index

| #                                                                    | Decision                                                                  | Status   |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------- | -------- |
| [0001](0001-strangler-re-architecture.md)                            | 1.0 is a strangler re-architecture, not a rewrite or a refactor           | Accepted |
| [0002](0002-php-8-1-and-wordpress-6-4-floor.md)                      | PHP 8.1 and WordPress 6.4 are the 1.x floor; 0.x stays on PHP 8.0         | Accepted |
| [0003](0003-gpl-2-0-or-later.md)                                     | The library is GPL-2.0-or-later                                           | Accepted |
| [0004](0004-ports-and-adapters-at-real-seams.md)                     | Ports and adapters, only at real seams                                    | Accepted |
| [0005](0005-multiple-copies-coexist-via-scoping.md)                  | Multiple copies coexist through scoping, prefixed globals, loud detection | Accepted |
| [0006](0006-library-text-domain-and-consumer-prefixes.md)            | Library strings use `wptoolkit`; everything global uses the consumer's slug | Accepted |
| [0007](0007-own-container-no-static-registry.md)                     | Our own small container; no static registry, no enforced singletons       | Accepted |
| [0008](0008-deny-by-default-one-http-pipeline.md)                    | Deny by default; one middleware pipeline for Ajax and REST                | Accepted |
| [0009](0009-entity-and-repository-not-active-record.md)              | Entities and repositories replace the active-record `Model`               | Accepted |
| [0010](0010-testing-strategy.md)                                     | Unit with Brain Monkey, integration with wp-phpunit, E2E with Playwright  | Accepted |
| [0011](0011-conventional-commits-semver-release-automation.md)       | Conventional Commits, SemVer, automated releases                          | Accepted |
| [0012](0012-zero-runtime-dependencies.md)                            | Zero runtime Composer dependencies, PSR via optional bridges              | Accepted |
| [0013](0013-plugins-boot-through-a-syntax-safe-guard.md)             | Every plugin boots through a syntax-safe guard and never takes the site down | Accepted |
| [0014](0014-composer-is-optional.md)                                 | Composer is optional: standalone build, tiny autoloader, zero-tool scoper | Accepted |
| [0015](0015-packaging-is-a-toolkit-dev-tool.md)                      | Packaging is a toolkit dev tool (PHP CLI) that CI calls, not runtime code | Accepted |
| [0016](0016-1-0-reads-0x-data-unchanged.md)                          | 1.0 reads and writes 0.x data unchanged; only the PHP API breaks          | Accepted |
