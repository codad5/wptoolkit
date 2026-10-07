# Phase 0 — Stabilize 0.x and lay the gates

**Effort:** S (~1 active day) · **Depends on:** nothing · **Unblocks:** everything

---

## Goal

**Make today's code safe for the people already using it, and put the quality gates in place before
any new code is written.**

`pau-alumni-manager` runs 0.x in production with three access-control holes (S1–S3). Those are fixed
on `0.x` first, with tests that fail before the fix. The tooling and CI built here are then reused,
unchanged, by every later phase.

> Resist improving 0.x. Fix what is unsafe or broken, add the gates, stop. Everything else is 1.0's
> job.

---

## Scope

### 0.1 Repository and release hygiene — on `main` / `0.x`

- [ ] Merge [PR #4](https://github.com/codad5/wptoolkit/pull/4) (`dev → main`) *(maintainer)*
- [ ] Tag `v0.1.0` at `9e4473c` (main before #4) and `v0.2.0` after #4 *(maintainer)*
- [ ] Create the `0.x` branch from `v0.2.0`; protect `main` and `0.x`
- [ ] Delete the merged local branch `fixing-frontend-page-and-enqueuing`
- [ ] `git rm -r --cached .idea/` and ignore it
- [ ] Check what in `src/` uses `assets/chosen-v1.8.7/`; keep only the minified runtime files, drop
      `docsupport/` (bundles jQuery 1.12.4) and the demo HTML
- [ ] Add `LICENSE` (GPL-2.0-or-later); fix `composer.json` `license`
      ([ADR-0003](../adr/0003-gpl-2-0-or-later.md))
- [ ] `composer.json`: `"php": ">=8.0"`, description, keywords, `support`; `.gitattributes`
      `export-ignore` for `sample-plugins/`, `.idea/`, `*.md` except README
- [ ] Remove the fake "Build: Passing" badge from README (the real one comes in 0.5)

### 0.2 Security hotfix → `v0.2.1` — test first, every one

- [ ] **S1** `Model::handle_ajax_search` / `handle_ajax_autocomplete`: register `nopriv` only when the
      post type is public and doesn't require authentication; respect `REQUIRES_AUTHENTICATION` and
      `VIEW_CAPABILITY`; cap `limit` at 50; only include meta for users who can `edit_posts`
- [ ] **S2** `MetaBox::handle_ajax`: drop `nopriv`; check post type matches the metabox's screens;
      check `current_user_can('edit_post', $post_id)`
- [ ] **S3** `RestRoute::addRoute`: default `permission_callback` becomes "logged in"; `__doing_it_wrong`
      when a route has none; document `'public' => true` as the explicit opt-out
- [ ] **S4** `Ajax::handleRequest` and `Model` search: generic message to the client, details to the log
- [ ] Regression tests: anonymous user cannot read private meta (S1, S2); route without permission
      is not open (S3); exception text never reaches the response body (S4)

### 0.3 Correctness hotfix (same release)

- [ ] **C2** `Ajax::error()` sends the HTTP status (`wp_send_json_error($response, $code)`)
- [ ] **C4** `Debugger` buffers console output and prints in footer hooks (per `BUGFIX_INSTRUCTIONS.md`),
      then delete `BUGFIX_INSTRUCTIONS.md`
- [ ] **C5** No translation calls before `init`
- [ ] Deferred to 1.0 (written down, not fixed here): **C1** taxonomy search, **C3** rate limiter

### 0.4 Tooling — shared with `next`

- [ ] Composer dev deps: PHPUnit, Brain Monkey, PHPStan + `szepeviktor/phpstan-wordpress`, PHPCS +
      WPCS + PHPCompatibilityWP; pinned in [tech-stack.md](../reference/tech-stack.md)
- [ ] `phpunit.xml.dist`, `phpstan.neon.dist` (0.x: level 5 + baseline), `phpcs.xml.dist`
- [ ] Composer scripts: `test`, `lint`, `analyse`, `fix`, `verify` (= lint + analyse + test)
- [ ] `.gitmessage` and commit-message check in CI
      ([ADR-0011](../adr/0011-conventional-commits-semver-release-automation.md))

### 0.5 CI

- [ ] `.github/workflows/ci.yml`: validate (composer validate + audit) · lint · analyse · unit
      (PHP 8.0–8.4 on `0.x`; 8.1–8.4 on `next`)
- [ ] `dependabot.yml` (composer, github-actions)
- [ ] Real CI badge in README

### 0.6 Agent and docs scaffolding

- [x] `CLAUDE.md`, `AGENTS.md`, `.claude/rules/`, `STATUS.md`
- [x] `docs/` — architecture, ADRs 0001–0012, phases, reference
- [ ] PR and issue templates, `CODEOWNERS`

---

## Definition of Done

- Tags `v0.1.0`, `v0.2.0` and **`v0.2.1`** exist; `0.x` is protected.
- Each of S1–S4 has a test that **fails on `v0.2.0` and passes on `v0.2.1`** — demonstrated by
  running it against both.
- `pau-alumni-manager` runs `v0.2.1` with `WP_DEBUG` on and shows none of the notices listed in the
  old `BUGFIX_INSTRUCTIONS.md`.
- CI is green on `0.x` and `next`, and the README badge reflects it.

## Deliberately not in this phase

Any new feature. Any refactor of 0.x. The search fix (C1) and the rate limiter (C3) — both need
designs that only exist in 1.0.

## Risks

| Risk                                                   | Mitigation                                                  |
| ------------------------------------------------------ | ----------------------------------------------------------- |
| S3's new default breaks a consumer's public route      | Release note; `__doing_it_wrong` names the route and the fix |
| Brain Monkey can't express a WP behaviour we rely on   | Integration test in Phase 1's wp-phpunit harness instead     |
