# Track P — Packager (parallel, not a phase)

**Effort:** S–M (~0.5–0.75 active day) · **Depends on:** Phase 0's tooling; scoping step needs Phase 1 §1.5
· **Decision:** [ADR-0015](../adr/0015-packaging-is-a-toolkit-dev-tool.md) (Accepted)

---

## Goal

**One `wptoolkit package` command builds a correct, verified zip for any plugin or theme, the same
way locally and in CI — replacing the three forked `build-tools/prepare-*.js` scripts.**

It doesn't depend on the 1.0 core, so it can start right after Phase 0 and ship before 1.0. It
fixes live problems in `pau`, `silverbird-fusionintel` and `nile-distribution` today.

---

## How it's used

**Primary interface — a fluent `build.php`** (ADR-0015 amendment):

```php
<?php
// build.php — run with: php build.php
require __DIR__ . '/vendor/autoload.php';

use Codad5\WPToolkit\Build\{Build, Version};

Build::plugin(__DIR__)                                   // or Build::theme(__DIR__)
    ->version(Version::fromPackageJson())                // fromComposerJson(), fromHeader(), fromGitTag(), fromConstant()
    ->syncVersionTo('header', 'readme.txt', 'MY_PLUGIN_VERSION')
    ->run('npm ci', 'npm run build')                     // project asset build, before staging
    ->composer(noDev: true)                              // inside the staging copy
    ->scope('MyPlugin\WPToolkit')
    ->makePot()
    ->include('assets/dist')
    ->exclude('src/**/*.ts', 'docs', '*.map')
    ->step(new MyCustomStep())                           // any BuildStep
    ->verify()
    ->zip('dist/{slug}-{version}.zip');
```

Building blocks: `Build` (builder), `BuildStep` (interface: `name()`, `run(BuildContext)`),
`BuildContext` (paths, slug, version, file list, report), `Version` sources (Strategy), and the
built-in steps behind each method. A failing step stops the build with its name and a non-zero
exit code.

**Zero config by default.** In most projects you run it with no setup, and it reads the plugin or
theme headers:

```bash
vendor/bin/wptoolkit package              # Composer install
php wptoolkit/bin/wptoolkit package       # standalone install (no Composer)
```

| Command                              | Does                                                                    |
| ------------------------------------ | ----------------------------------------------------------------------- |
| `wptoolkit init`                     | Writes a starter `wptoolkit.json` from the detected headers              |
| `wptoolkit package`                  | Build → stage → zip → verify                                             |
| `wptoolkit package --dry-run`        | Prints the exact file list that would ship; writes nothing               |
| `wptoolkit verify dist/x.zip`        | Runs only the verification on an existing zip                            |
| `wptoolkit doctor`                   | Checks headers, guard arguments and `wptoolkit.json` agree; lints the main file on old-PHP syntax |
| `wptoolkit scope <Prefix>`           | Scopes the bundled toolkit (ADR-0014)                                    |

Flags override config: `--out`, `--zip-name`, `--no-build`, `--no-composer`, `--no-scope`, `--pot`,
`--json` (machine-readable report for CI).

**Configuration** is one optional `wptoolkit.json` at the project root. It's JSON so PHP reads it
without dependencies, and it has a JSON Schema so editors autocomplete it:

```json
{
  "$schema": "https://raw.githubusercontent.com/codad5/wptoolkit/main/schema/wptoolkit.schema.json",
  "type": "plugin",
  "main": "pau.php",
  "slug": "pau-alumni-manager",
  "requires": { "php": "8.1", "wp": "6.4", "extensions": ["mbstring"] },
  "package": {
    "out": "dist",
    "zipName": "{slug}-{version}.zip",
    "build": ["npm ci", "npm run build"],
    "composer": "no-dev",
    "scope": { "prefix": "FIT\\PAUAlumniManager\\Vendor" },
    "pot": true,
    "exclude": ["docs/", "*.map"],
    "include": ["vendor/codad5/wptoolkit/languages/"],
    "verify": { "forbid": ["*.sql", "*.env"], "maxSizeMb": 5 }
  }
}
```

- Every key is optional. Defaults: `type`/`main`/`slug` detected; `out` = project root;
  `zipName` = `{slug}.zip`; `composer` = `no-dev`; scope off; verification on.
- **`requires` is the single source of truth.** `doctor` and `verify` fail if the `Requires PHP` /
  `Requires at least` headers or the guard call disagree with it.
- Ignore rules: `.distignore` (WP-CLI format) if present, plus `package.exclude`; `package.include`
  re-adds paths an ignore rule removed.
- Built-in forbidden list (dev deps, `.git`, `.claude`, `node_modules`, `tests`, …) can be extended
  but not silently disabled; `--allow <pattern>` exists for a deliberate exception and is printed
  in the report.

**From npm and CI:**

```json
"scripts": { "package": "php vendor/bin/wptoolkit package" }
```

```yaml
jobs:
  release:
    uses: codad5/wptoolkit/.github/workflows/package-wordpress.yml@v1
    with: { php: "8.3", node: "20" }
```

---

## Scope

### P.0 Fluent build library

- [x] `Build`, `BuildStep`, `BuildContext`, `Version` sources; every built-in step a class
- [x] `syncVersionTo()`: write one version into the header, `readme.txt` Stable tag and a constant
- [x] Pattern sources for `exclude()`/`include()` besides plain globs (maintainer's idea):
      `Patterns::fromDistignore()`, `fromGitattributesExportIgnore()`, `fromGitignore()`. Docs steer
      to `.distignore`: a `.gitignore` excludes build output that should ship (`assets/dist`,
      `vendor`) and misses dev files that shouldn't; `verify()` warns when an excluded file is one
      the plugin references
- [x] Shipped as `vendor/bin/wptoolkit-build` inside `codad5/wptoolkit` (a separate
      `codad5/wptoolkit-build` package is prepared in `packages/build/composer.json`)
- [x] `wptoolkit.json` / `wptoolkit package` build the same pipeline

### P.1 Command and detection

- [x] `wptoolkit-build` entry with a `package` subcommand (PHP 8.1, like the library)
- [x] Detect plugin (main file `Plugin Name:` header) or theme (`style.css` `Theme Name:`); read
      slug, version, `Requires PHP`, `Requires at least`, text domain. Plus `Build::library()` for
      WPToolkit's own zip
- [x] Subcommands `init`, `package`, `verify`, `doctor` (`scope` stays `bin/scope.php` / the
      `scope` key in wptoolkit.json)
- [x] `wptoolkit.json` loader with defaults (a JSON schema file is deferred to 1.1)

### P.2 Staging pipeline

- [x] Copy to a temp staging dir; never mutate the working tree
- [x] Optional build hook from config (`npm run build`) run **before** staging
- [x] `composer install --no-dev --optimize-autoloader` in staging when `composer.json` exists
- [x] Scope the bundled toolkit when configured (Strauss for Composer, `bin/scope.php` for standalone)
- [x] Optional `wp i18n make-pot`
- [x] `.distignore` (WP-CLI format) + built-in defaults; globs relative to the root
- [ ] `php -l` every PHP file in staging

### P.3 Zip and verify

- [x] Zip under one top-level folder named after the slug; deterministic file order
- [x] Verification failures: dev deps in `vendor/`, `vendor/bin`, `.git`, `.github`, `.claude`,
      `.agents`, `.idea`, `node_modules`, `tests`, `*.zip`, `composer.lock`/`package*.json`,
      header/guard version mismatch
- [x] Report: size, file count, largest directories; write `<zip>.sha256`
- [x] Exit codes: 0 success, 1 verification failed, 2 build error

### P.4 CI wrapper

- [x] Reusable workflow `.github/workflows/package-wordpress.yml` (`workflow_call`): checkout →
      PHP (+ Node if a build hook exists) → `doctor` → `package` → `verify` → artifact, and the Release on tags
- [x] Example caller workflow (in the reusable workflow's header; the same for plugins and themes)

### P.5 Migrate the three projects

- [ ] `pau`: replace `prepare-plugin.js`; confirm the zip drops from ~8.9 MB and has no dev tooling
- [ ] `silverbird-fusionintel`, `nile-distribution`: replace `prepare-theme.js` and `release.yml`;
      confirm `.claude/` and `sample-plugins/` are gone
- [ ] Remove `archiver` from each `package.json`

---

## Definition of Done

> **Progress 2026-10-07:** library + `wptoolkit-build package|init` built and tested (reproducible zips,
> verification). Run against a copy of `pau`, it refuses the 2,623 dev-tool files the old script shipped.
> Still open: `doctor`, `verify <zip>`, `--dry-run`, size report details, the reusable CI workflow, and
> migrating the three projects (needs the maintainer's go-ahead — they are separate repositories).

- Fixture plugin and fixture theme: the zip's file list **exactly** matches a committed snapshot.
- Packaging a project with dev dependencies installed locally produces a zip with **none** of them.
- A project whose `Requires PHP` disagrees with its guard config fails verification with a message
  naming both values.
- The same command produces the same zip (same SHA-256) locally and in CI from the same commit.
- All three real projects build with it, and their old scripts are deleted.

## Deliberately not in this track

Uploading to WordPress.org SVN. Deploying to servers. Asset bundling itself (that stays the
project's own `npm run build`; we only call it).
