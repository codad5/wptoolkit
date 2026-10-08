# 00 — Executive summary

**WPToolkit** is a library WordPress plugin developers bundle to build plugins that are typed,
testable, translatable and **secure by default** — without adopting a heavy framework or a toolchain
they don't want.

## Where it stands (2026-10-07)

0.x is an ambitious, unreleased, untested library (~17k lines, 20 classes, one known production
consumer: `member-directory`). A review scored it 50/100: good API shape, a weak foundation, three
access-control holes. 1.0 keeps the API shape and rebuilds the foundation
([ADR-0001](../adr/0001-strangler-re-architecture.md)).

## What 1.0 promises a plugin developer

1. **Your plugin can't take a site down** by being installed on the wrong PHP or WordPress version,
   or by failing during boot ([ADR-0013](../adr/0013-plugins-boot-through-a-syntax-safe-guard.md)).
2. **Your plugin and someone else's can bundle different WPToolkit versions** on one site without
   interfering ([ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md)).
3. **Every endpoint is closed until you open it**, and nonces, capabilities, validation and rate
   limits are one line each ([ADR-0008](../adr/0008-deny-by-default-one-http-pipeline.md)).
4. **Every backend is swappable** — cache, HTTP, logging, storage — without editing the library
   ([ADR-0004](../adr/0004-ports-and-adapters-at-real-seams.md)).
5. **Fully localizable**, including JavaScript and right-to-left layouts ([04](04-localization.md)).
6. **No forced toolchain**: works with Composer or as a plain zip
   ([ADR-0014](../adr/0014-composer-is-optional.md)); zero runtime dependencies
   ([ADR-0012](../adr/0012-zero-runtime-dependencies.md)).

## The shape

```
 my-plugin.php  ── guard (PHP 5.6 syntax) ──▶ checks pass? ──▶ Application ──▶ ServiceProviders
                                                   │ no               │
                                                   ▼                  ▼
                                      inert + admin notice    Http · Data · View · Admin · Assets
                                                                       │
                                                         Contracts ◀───┘──▶ Adapters ──▶ WordPress
```

## Read next

[01 Principles](01-principles-and-constraints.md) · [02 System architecture](02-system-architecture.md)
· [03 Coexistence](03-multi-version-coexistence.md) · [04 Localization](04-localization.md)
· [05 Quality, testing, CI](05-quality-testing-ci.md) · [06 Security](06-security.md)
· [07 Migration from 0.x](07-migration-from-0x.md) · [08 Out of scope](08-out-of-scope.md)
· [Phases](../phases/README.md)
