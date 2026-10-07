# Phase 0 — Lay the gates

**Effort:** S (~0.5 active day) · **Depends on:** nothing · **Unblocks:** everything

> **Changed 2026-10-07 (maintainer's call):** 0.x is frozen and deprecated, so this phase no longer
> ships a 0.x hotfix. It only puts the quality gates in place on `next`. The parked 0.x security
> work lives on branch `fix/0.2.1-security` (unreleased, tests never run) — see
> [track-0x-maintenance.md](track-0x-maintenance.md).

---

## Goal

**Before any 1.0 code is written, a push to `next` runs lint, static analysis and tests in CI, and
the same checks run locally with one command.**

---

## Scope

### 0.1 Repository hygiene (on `next`)

- [ ] `git rm -r --cached .idea/` and ignore it
- [ ] `LICENSE` (GPL-2.0-or-later); `composer.json` license, PHP constraint, metadata
      ([ADR-0003](../adr/0003-gpl-2-0-or-later.md))
- [ ] `.gitattributes`: `* text=auto eol=lf`; `export-ignore` for `tests/`, `docs/`, `examples/`,
      `.github/`, `.claude/`, dotfiles
- [ ] Remove the fake "Build: Passing" badge from README

### 0.2 Tooling

- [ ] Composer dev deps: PHPUnit, Brain Monkey, PHPStan + `szepeviktor/phpstan-wordpress`,
      PHPCS + WPCS + PHPCompatibilityWP; versions recorded in
      [tech-stack.md](../reference/tech-stack.md)
- [ ] `config.platform.php` = 8.1 so dependencies resolve for the floor, not the dev machine
- [ ] `phpunit.xml.dist`, `phpstan.neon.dist` (level 8 on `src/`; `legacy/` excluded until ported),
      `phpcs.xml.dist`
- [ ] Composer scripts: `lint`, `cs`, `analyse`, `test`, `verify`
- [ ] Shared test harness: Brain Monkey base `TestCase`, WordPress class stubs, JSON-response capture
      (carried over from the parked branch)

### 0.3 CI

- [ ] `.github/workflows/ci.yml` on `next` and PRs: validate (composer validate + audit) · lint ·
      cs · analyse · unit (PHP 8.1 → newest stable)
- [ ] `dependabot.yml` (composer, github-actions)
- [ ] Real CI badge in README

### 0.4 Agent and docs scaffolding

- [x] `CLAUDE.md`, `AGENTS.md`, `.claude/rules/`, `STATUS.md`
- [x] `docs/` — architecture, ADRs, phases, reference

---

## Definition of Done

- `composer verify` passes locally on a clean clone.
- A push to `next` runs every CI job green, and the README badge reflects it.
- A deliberately broken commit (syntax error, PHPStan error, failing test) turns CI red in the job
  named for that concern.

## Deliberately not in this phase

Any 0.x release (0.x is frozen). Integration and E2E infrastructure (Phase 1 §1.7). Coverage gates
(once there is code to cover).
