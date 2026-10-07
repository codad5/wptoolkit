# ADR-0015 — Packaging is a WPToolkit dev tool (PHP CLI), which CI calls; it is not part of the runtime

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `bin/wptoolkit`, [track-p-packager.md](../phases/track-p-packager.md), Phase 6

## Context

The maintainer packages every WordPress project with a hand-copied `build-tools/prepare-*.js`
(Node + `archiver`). Inspected on 2026-10-07 across three projects:

| Project                     | Script                | What the shipped zip actually contains                                  |
| --------------------------- | --------------------- | ----------------------------------------------------------------------- |
| `pau` (plugin)              | `prepare-plugin.js`   | 8.9 MB; **~3,000 files of dev tooling in `vendor/`** — PHP_CodeSniffer, PHPStan, WPCS, Slevomat, stubs — because it zips the local `vendor/` as-is |
| `silverbird-fusionintel` (theme) | `prepare-theme.js` | 9.6 MB, 1,121 files; **`.claude/skills/` (127 files) shipped to the client site**; WPToolkit's `sample-plugins/`; files at the zip root with no theme folder |
| `nile-distribution` (theme) | `prepare-theme.js`    | Same script as silverbird with different exclude patterns                |

Three copies, three checksums: the script has already forked. Defects found:

- **The theme script always exits with code 1** (`process.exit(1)` in `finally`), so
  `npm run theme:build` reports failure even when it succeeds — in CI that fails the release job.
- Exclusion uses `filePath.includes(pattern)` on the **absolute** path, so a pattern like `vendor`
  also drops `assets/js/vendor-slider.js`, and results depend on where the project is checked out.
- The theme names the zip from `composer.json`'s `vendor/name` without splitting the slash.
- Theme release workflows zip **before** `composer install --no-dev` and then run `composer phpcs`
  after installing without dev dependencies — so the installed tools never reach the zip, and the
  lint has no PHP_CodeSniffer to run.
- Nothing verifies the zip: no check for dev files, `.git`, agent config, `Requires PHP` matching the
  code, or the expected top-level folder.

Packaging is where several WPToolkit decisions are actually applied: scoping (ADR-0005, ADR-0014),
the guard's PHP version matching `Requires PHP` (ADR-0013), translations (ADR-0006).

## Decision

WPToolkit ships a packager as a **dev-time command**, `wptoolkit package`, in `bin/`:

- **Not part of the runtime.** It lives in `bin/` (like `scope.php`), is never autoloaded by a
  plugin, and is excluded from the plugin's own zip.
- **Written in PHP** with `ZipArchive` — the only tool every WordPress developer already has
  (ADR-0014: don't force Node or Composer). npm scripts and CI just call it.
- **Plugins and themes**, detected from the `Plugin Name:` header or `style.css`.
- **Ignore rules** in `.distignore` (the same format `wp dist-archive` uses), matched as globs
  **relative to the project root**, with safe built-in defaults — most projects need no file.
- **Works on a staging copy**, never the working tree:
  read headers → optional build hook (`npm run build`) → `composer install --no-dev
  --optimize-autoloader` in staging → scope the toolkit if configured → optional `make-pot` →
  apply ignores → `php -l` → zip under **one top-level folder named after the slug** → **verify**.
- **Verification fails the build** if the zip contains dev dependencies (`vendor/bin`, `phpstan`,
  `squizlabs`, …), `.git`, `.claude`/`.agents`, `node_modules`, `tests`, other zips, or if
  `Requires PHP` / `Version` disagree with the guard config and version constant. It prints size,
  file count, the largest directories, and writes a `.sha256`.
- **Correct exit codes** (0 success, non-zero failure).
- **CI is a thin wrapper:** a reusable workflow in this repository that runs the same command on a
  tag and attaches the zip to the GitHub Release. Local build ≡ CI build.

## Options considered

### Option A — Leave it in each project's CI/CD

**Pros:** no library involvement. **Cons:** it is already copied three times and has forked; every
copy carries the same bugs; CI and local builds differ. **Lost.**

### Option B — Put it in the runtime library

**Lost:** build code would ship into production sites and the `archiver` dependency breaks
ADR-0012.

### Option C — Use `wp dist-archive` (WP-CLI)

**Pros:** exists, `.distignore`-based. **Cons:** needs WP-CLI; doesn't stage a `--no-dev` install,
scope, or verify the result — which are exactly the failures found above. **Lost**, but we keep its
`.distignore` format so projects can switch either way.

### Option D — Toolkit dev tool + thin CI wrapper (proposed)

## Consequences

**We accept:** maintaining a packager and its tests (fixture plugin and theme → assert the zip's
exact file list).

**We gain:** one tested definition of "what ships"; no more dev tooling or agent config on client
sites; packaging that knows about scoping and the guard.

**This constrains:** WPToolkit projects drop `build-tools/prepare-*.js` and `archiver`; the packager
must stay zero-dependency PHP.

## Revisit when

WP-CLI's `dist-archive` gains staging and verification — then make ours a thin layer over it.
