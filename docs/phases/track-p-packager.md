# Track P — Packager (parallel, not a phase)

**Effort:** S–M (~0.5–0.75 active day) · **Depends on:** Phase 0's tooling; scoping step needs Phase 1 §1.5
· **Decision:** [ADR-0015](../adr/0015-packaging-is-a-toolkit-dev-tool.md) (Proposed)

---

## Goal

**One `wptoolkit package` command builds a correct, verified zip for any plugin or theme, the same
way locally and in CI — replacing the three forked `build-tools/prepare-*.js` scripts.**

It doesn't depend on the 1.0 core, so it can start right after Phase 0 and ship before 1.0. It
fixes live problems in `pau`, `silverbird-fusionintel` and `nile-distribution` today.

---

## Scope

### P.1 Command and detection

- [ ] `bin/wptoolkit` entry (PHP 7.4+ syntax, so it also runs for 0.x projects); `package` subcommand
- [ ] Detect plugin (main file `Plugin Name:` header) or theme (`style.css` `Theme Name:`); read
      slug, version, `Requires PHP`, `Requires at least`, text domain
- [ ] Options: `--out`, `--no-build`, `--no-composer`, `--scope`, `--pot`, `--dry-run` (list files only)

### P.2 Staging pipeline

- [ ] Copy to a temp staging dir; never mutate the working tree
- [ ] Optional build hook from config (`npm run build`) run **before** staging
- [ ] `composer install --no-dev --optimize-autoloader` in staging when `composer.json` exists
- [ ] Scope the bundled toolkit when configured (Strauss for Composer, `bin/scope.php` for standalone)
- [ ] Optional `wp i18n make-pot`
- [ ] `.distignore` (WP-CLI format) + built-in defaults; globs relative to the root
- [ ] `php -l` every PHP file in staging

### P.3 Zip and verify

- [ ] Zip under one top-level folder named after the slug; deterministic file order
- [ ] Verification failures: dev deps in `vendor/`, `vendor/bin`, `.git`, `.github`, `.claude`,
      `.agents`, `.idea`, `node_modules`, `tests`, `*.zip`, `composer.lock`/`package*.json`,
      header/guard version mismatch
- [ ] Report: size, file count, 10 largest directories; write `<zip>.sha256`
- [ ] Exit codes: 0 success, 1 verification failed, 2 build error

### P.4 CI wrapper

- [ ] Reusable workflow `.github/workflows/package-wordpress.yml` (`workflow_call`): checkout →
      PHP (+ Node if a build hook exists) → `php wptoolkit package` → upload to the Release
- [ ] Example caller workflow for a plugin and a theme

### P.5 Migrate the three projects

- [ ] `pau`: replace `prepare-plugin.js`; confirm the zip drops from ~8.9 MB and has no dev tooling
- [ ] `silverbird-fusionintel`, `nile-distribution`: replace `prepare-theme.js` and `release.yml`;
      confirm `.claude/` and `sample-plugins/` are gone
- [ ] Remove `archiver` from each `package.json`

---

## Definition of Done

- Fixture plugin and fixture theme: the zip's file list **exactly** matches a committed snapshot.
- Packaging a project with dev dependencies installed locally produces a zip with **none** of them.
- A project whose `Requires PHP` disagrees with its guard config fails verification with a message
  naming both values.
- The same command produces the same zip (same SHA-256) locally and in CI from the same commit.
- All three real projects build with it, and their old scripts are deleted.

## Deliberately not in this track

Uploading to WordPress.org SVN. Deploying to servers. Asset bundling itself (that stays the
project's own `npm run build`; we only call it).
