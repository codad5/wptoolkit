# Phase 7 — Release 1.0

**Effort:** S (~0.5 active day + dogfood wait) · **Depends on:** Phase 6 · **Unblocks:** 1.0.0

---

## Goal

**Ship 1.0.0 only after a real production plugin has run on it.**

---

## Scope

### 7.1 Release automation ([ADR-0011](../adr/0011-conventional-commits-semver-release-automation.md))

- [ ] `release.yml`: on tag `v*` → build the export-ignored dist zip **and the standalone zip**
      ([ADR-0014](../adr/0014-composer-is-optional.md)), generate the changelog from Conventional
      Commits, publish the GitHub Release with both zips attached, ping Packagist
- [ ] `Application::VERSION` bumped by the workflow, and the build fails if it doesn't match the tag
- [ ] Packagist package registered *(maintainer)*

### 7.2 Hardening

- [ ] Security checklist ([06-security.md](../architecture/06-security.md)) walked end to end
- [ ] Psalm taint analysis clean on `src/`
- [ ] Performance: boot time with 10 providers, and route dispatch, measured on wp-env; numbers in
      the work log (budget: < 5 ms added to a request that touches no toolkit route)

### 7.3 Release candidate and dogfood

- [ ] `v1.0.0-rc.1`
- [ ] Migrate `pau-alumni-manager` to 1.0 (scoped) on staging using only the migration guide; log
      every place the guide was wrong and fix the guide
- [ ] Run staging for an agreed soak period with `WP_DEBUG_LOG` on; zero toolkit warnings

### 7.4 Release

- [ ] `v1.0.0`; merge `next` → `main`; `dev` retired
- [ ] `0.x` support policy published: security fixes for 12 months after 1.0.0

---

## Definition of Done

- `composer require codad5/wptoolkit:^1.0` installs from Packagist, and the dist zip contains no
  tests, examples, docs or dotfiles.
- A developer without Composer downloads the standalone zip, runs `php bin/scope.php TheirPlugin`,
  and boots a plugin following only the getting-started guide.
- `pau-alumni-manager` runs 1.0 in production.
- Every earlier phase's DoD still passes in CI.

## Deliberately not in this phase

New features. Everything that arrives now goes to 1.1.
