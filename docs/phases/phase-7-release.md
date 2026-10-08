# Phase 7 — Release 1.0

**Effort:** S (~0.5 active day + dogfood wait) · **Depends on:** Phase 6 · **Unblocks:** 1.0.0

---

## Goal

**Ship 1.0.0 only after a real production plugin has run on it.**

---

## Scope

### 7.1 Release automation ([ADR-0011](../adr/0011-conventional-commits-semver-release-automation.md))

- [x] `release.yml`: on tag `v*` → every CI gate (reused via `workflow_call`) → the standalone zip
      ([ADR-0014](../adr/0014-composer-is-optional.md)) + `.sha256`, verified → changelog from
      Conventional Commits (`bin/changelog.php`) → GitHub Release (pre-release for `-rc` tags).
      Composer users get the GitHub dist archive (export-ignored) through the VCS repository, so no
      separate dist zip and no Packagist ping
- [x] `Application::VERSION` set by `php bin/set-version.php X.Y.Z` (also the JS client's VERSION) in
      the release commit; the workflow fails if either doesn't match the tag
- [ ] Packagist package registered *(maintainer)*

### 7.1b The build tool ships with 1.0 (Track P)

- [x] Track P complete: `doctor`, `verify <zip>`, `--dry-run`, reusable `package-wordpress.yml`
- [x] The build tool ships inside `codad5/wptoolkit` as `vendor/bin/wptoolkit-build` (root `bin`);
      `packages/build/composer.json` is ready to split out as `codad5/wptoolkit-build` later
- [x] The release workflow builds WPToolkit's own standalone zip **with** the build tool (dogfooding:
      `Build::library()`, `bin/build-release.php`)
- [ ] At least one real project (member-directory) packaged with it — with the maintainer's go-ahead

### 7.2 Hardening

- [x] Security checklist ([06-security.md](../architecture/06-security.md)) walked end to end (Psalm deferred, see there)
- [ ] Psalm taint analysis clean on `src/`
- [ ] Performance: boot time with 10 providers, and route dispatch, measured on wp-env; numbers in
      the work log (budget: < 5 ms added to a request that touches no toolkit route)

### 7.3 Release candidate and dogfood

- [ ] `v1.0.0-rc.1`
- [ ] Migrate `member-directory` to 1.0 (scoped) on staging using only the migration guide; log
      every place the guide was wrong and fix the guide
- [ ] Run staging for an agreed soak period with `WP_DEBUG_LOG` on; zero toolkit warnings

### 7.3b Protect consumers that track `dev-main`

- [ ] `member-directory` requires `codad5/wptoolkit: dev-main`: before 1.0 reaches `main`, pin it to the last 0.x
      tag (or migrate it to 1.0 deliberately). Otherwise its next `composer update` pulls 1.0, which
      needs PHP 8.1 while member-directory declares 8.0 *(maintainer)*
- [ ] Tag the last 0.x (`v0.2.0`) on the current `main` first, so there is something to pin to

### 7.4 Release

- [ ] Open the PR `next` → `main` with the full change description; the maintainer reviews and merges
- [ ] `v1.0.0` GitHub release (tag + standalone zip + `.sha256` built with `wptoolkit-build`); no
      Packagist needed — Composer users can install from the GitHub repository (VCS); `dev` retired
- [ ] `0.x` support policy published: security fixes for 12 months after 1.0.0

---

## Definition of Done

- `composer require codad5/wptoolkit:^1.0` installs from Packagist, and the dist zip contains no
  tests, examples, docs or dotfiles.
- A developer without Composer downloads the standalone zip, runs `php bin/scope.php TheirPlugin`,
  and boots a plugin following only the getting-started guide.
- `member-directory` runs 1.0 in production.
- Every earlier phase's DoD still passes in CI.

## Deliberately not in this phase

New features. Everything that arrives now goes to 1.1.
