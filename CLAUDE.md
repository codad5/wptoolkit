# CLAUDE.md — Agent Operating Rules for WPToolkit

You are contributing to a **library** that other people's WordPress plugins bundle and ship to
production sites. A bug here is a bug in every plugin that uses it. Read this file fully before
writing code. It is binding. Where it conflicts with your defaults, this file wins.

---

## 1. Read before you write

| Task touches…                              | Load first                                                                                                                         |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------- |
| Anything at all                            | this file, [docs/architecture/00-executive-summary.md](docs/architecture/00-executive-summary.md), [STATUS.md](STATUS.md)          |
| Which task to do next                      | [docs/phases/README.md](docs/phases/README.md) and the current phase doc                                                           |
| Any PHP                                    | [.claude/rules/20-php.md](.claude/rules/20-php.md), [.claude/rules/10-architecture-boundaries.md](.claude/rules/10-architecture-boundaries.md) |
| Writing tests (i.e. every change)          | [.claude/rules/60-testing.md](.claude/rules/60-testing.md)                                                                         |
| Endpoints, input, output, capabilities     | [.claude/rules/70-security.md](.claude/rules/70-security.md), [docs/architecture/06-security.md](docs/architecture/06-security.md)  |
| Any user-facing string, hook, handle, key  | [.claude/rules/75-i18n-and-naming.md](.claude/rules/75-i18n-and-naming.md), [ADR-0006](docs/adr/0006-library-text-domain-and-consumer-prefixes.md) |
| Anything global to WordPress               | [docs/architecture/03-multi-version-coexistence.md](docs/architecture/03-multi-version-coexistence.md) — **no exceptions**         |
| Porting a 0.x class                        | [docs/architecture/07-migration-from-0x.md](docs/architecture/07-migration-from-0x.md)                                             |
| Commits, branches, PRs                     | [.claude/rules/50-git-and-commits.md](.claude/rules/50-git-and-commits.md)                                                         |
| Docs, ADRs                                 | [.claude/rules/80-documentation.md](.claude/rules/80-documentation.md)                                                             |

---

## 2. The rules that are never negotiable

1. **Deny by default.** Every Ajax action and REST route declares who may call it. There is no
   default of "anyone". ([ADR-0008](docs/adr/0008-deny-by-default-one-http-pipeline.md))
2. **No global state.** No static registries, no enforced singletons, no `$GLOBALS` (one documented
   exception: the coexistence ledger). One `Application` per consuming plugin.
   ([ADR-0007](docs/adr/0007-own-container-no-static-registry.md))
3. **Everything that lands in WordPress's global namespace is prefixed by the consumer's slug** —
   hooks, Ajax actions, REST namespaces, option and transient keys, tables, cron hooks, script
   handles, JS globals, nonce actions. Never a bare `wptoolkit_` key.
   ([ADR-0005](docs/adr/0005-multiple-copies-coexist-via-scoping.md), [ADR-0006](docs/adr/0006-library-text-domain-and-consumer-prefixes.md))
4. **Core never names a concrete backend.** Domain code depends on our contracts in
   `src/Contracts`; WordPress and third-party specifics live in `src/Adapters`.
   ([ADR-0004](docs/adr/0004-ports-and-adapters-at-real-seams.md))
5. **Zero runtime Composer dependencies.** Not even `psr/*`.
   ([ADR-0012](docs/adr/0012-zero-runtime-dependencies.md))
6. **Every user-facing string is translatable**, with the right text domain and a
   `/* translators: */` comment when it has placeholders.
7. **Escape on output, sanitize on input, validate before use.** Late escaping, in the view layer.
8. **No class over ~400 lines, no method over ~40.** Split by responsibility, not by line count.
9. **PHP floor is 8.1** on `next`/1.x; **8.0** on `0.x`. Use nothing newer than the floor.
   ([ADR-0002](docs/adr/0002-php-8-1-and-wordpress-6-4-floor.md))
10. **Every decision a future reader could reasonably question becomes an ADR**, in the same PR as
    the code.
11. **A plugin built on WPToolkit must never take the site down.** `bootstrap/guard.php` and a
    consumer's main plugin file parse on PHP 5.6, define no global names, and load nothing —
    not even `vendor/autoload.php` — until the environment checks pass.
    ([ADR-0013](docs/adr/0013-plugins-boot-through-a-syntax-safe-guard.md))

---

## 3. How to work a task

1. **Locate it in the phase plan.** If it isn't in the current phase, say so before proceeding —
   phase order encodes risk, not preference.
2. **Check that phase's Definition of Done.** It is the acceptance bar.
3. **Plan before editing.** Name the files and which layer each sits in. If adding a backend means
   editing core, the plan is wrong.
4. **Tests ship in the same commit.** Write the test *first* for every bug fix (it must fail before
   the fix) and for every security control.
5. **Run the gates** before claiming done: `composer verify` (lint, analyse, test).
6. **Conventional Commits**, one logical change per commit.
7. **Report honestly.** Failing test → show the output. Skipped scope → say which and why.
8. **Tick the box** in the phase doc and update [STATUS.md](STATUS.md) when a task lands.

---

## 4. When you are unsure

- A trade-off with no obviously correct answer → write it into
  [docs/reference/open-decisions.md](docs/reference/open-decisions.md) with options and a
  recommendation, and raise it. Do not silently pick.
- A requirement conflicts with a rule here → stop and raise it.
- A tool or library version → [docs/reference/tech-stack.md](docs/reference/tech-stack.md). Don't
  guess, don't upgrade without a note there.

---

## 5. What you must not do

- Add a runtime dependency (see rule 5) or a dev dependency without a line in `tech-stack.md`.
- Use `$wpdb` outside a repository adapter, or build SQL without `$wpdb->prepare()`.
- Return exception messages, stack traces or SQL to a client.
- Enqueue or print assets outside `wp_enqueue_scripts` / `admin_enqueue_scripts` / footer hooks.
- Load translations before `init`.
- "Fix" a failing test by weakening its assertion.
- Change `0.x` for anything except security and data-loss fixes.

---

## 6. Branches

| Branch | What it is                                                                 |
| ------ | -------------------------------------------------------------------------- |
| `main` | Released code. Protected.                                                  |
| `0.x`  | Maintenance line for 0.x consumers. Security fixes only.                   |
| `next` | The 1.0 re-architecture. Merges to `main` at `1.0.0`.                      |
| `dev`  | Legacy integration branch; retired once `0.x` and `next` exist.            |
