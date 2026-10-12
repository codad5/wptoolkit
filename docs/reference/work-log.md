# Work log

One dated entry per working session: what landed, what was learned, what's next.

## 2026-10-07

- **Landed:** sync check (`dev` = `origin/dev` @ `11db13b`; local `main` fast-forwarded to
  `9e4473c`); 0.x review (50/100); branch `next`; agent and docs scaffolding: `CLAUDE.md`, rules,
  architecture 00–08, ADRs 0001–0014, phases 0–7 + 0.x track.
- **Learned:** the guard must live in an old-syntax file that returns a closure, because PHP parses
  a whole file before running it; `ParseError` from an *included* file is catchable on PHP 7+.
  Composer must be optional (traditional PHP developers), so scoping needs a zero-tool path.
- **Changed course:** merging PR #4 was blocked by the agent's safety check (merge without review).
  Started the 0.2.1 hotfix, then the maintainer froze 0.x: real exploitability on the three known
  sites is low (S1 needs a printed nonce none of them print; S2 needs a logged-in user; S3 already
  mitigated in member-directory). Parked the work on `fix/0.2.1-security` — **tests never run** (composer install
  hung). Found while doing it: admin-ajax runs with `is_admin()` true, so unrestricted `WP_Query`
  searches there include drafts.
- **Decided:** ADR-0016 — 1.0 keeps 0.x's stored data formats; security scenarios become named
  acceptance tests in Phases 3–4; migration guide built from the real consumers.
- **Next:** maintainer runs `composer install` on `next`; Phase 0 tooling + CI; Phase 1.

## 2026-10-08

- **Landed:** Phase 4 finished (search, columns, 0.x data shapes pinned, todo example, 0.x `Model`/`MetaBox`
  deleted); Phase 5 (views, assets, settings, admin pages, notices, public pages, `legacy/` deleted, E2E in
  English/Arabic/keyboard); Phase 6 (`wp {slug}` commands, README, guides, migration guide, `llms.txt`);
  Phase 7 automation (`release.yml`, `bin/build-release.php`, `package-wordpress.yml`).
- **Learned (bugs the tests caught):** 0.x stored non-media multiple values as one serialized row and
  sanitized custom prefixes — 1.0 had both wrong; entity fields named like post columns would have
  moved member-directory's `title` meta into `wp_posts` (columns are now opt-in); GET and POST on one Ajax path made
  the POST unreachable; an unknown arg type was a 500 at request time; block themes gave public pages
  no `<title>`; WP-CLI's `@subcommand` can't hold a colon; the meta API unslashes, so values need
  `wp_slash()`. Azure's apt mirror stalls Playwright installs — CI uses the runner's Chrome.
- **Next (maintainer):** tag the last 0.x and pin member-directory; review and merge the `next` → `main` PR; then
  `php bin/set-version.php 1.0.0-rc.1` → tag → staging soak with member-directory → `1.0.0`.


## 2026-10-11

- **Landed:** `Frontend\Shortcodes` (ADR-0022): prefixed or explicit tags, attributes validated like
  route args, a required access rule, view rendering, on-demand assets (`Asset::onDemand()`,
  `AssetManager::load()`), `make:shortcode`; the todo example gained `[wptk_todo_summary]` with an E2E test.
- **Learned:** the E2E job failed intermittently at login in global setup on a cold WordPress (also on
  `main`); setup now warms the site and retries. Tests for the shortcode controls were mutation-checked
  (17 deliberate faults, all caught).
- **Next (maintainer):** soak the release candidate on a real site, then `1.0.0`.
- **Also landed:** `Settings` accepts `optionPrefix:` (ADR-0023), restoring 0.x's `option_prefix` so plugins with
  existing option names upgrade without a data migration.
- **Fixed (reported by a consumer mid-migration):** `Build::scope()` left a Composer-based plugin's own code and
  autoload map on the unscoped namespace, so the zip could not load; and scaffolding stubs named the
  unscoped library. Both fixed with ADR-0024, a load check in the build, and an E2E test that boots a
  plugin packaged that way. **Learned:** a ticked box is not evidence — that path had no test that loaded its output.

## 2026-10-12

- **Landed:** the guard's `namespaces` option (ADR-0025): PSR-4 loading of a standalone plugin's own classes,
  which ADR-0014 promised and 0.x's `Autoloader::init()` used to do. The E2E fixtures now rely on it.
- **Landed:** `Build::verbose()`, `runVerbose()`, `--verbose` and `"verbose": true`: command output streamed as it
  runs (`StreamingCommandRunner`); a failure after streaming repeats only the last 20 lines.
- **Landed:** `Build::toDirectory()` (`--dir`) and `Build::quiet()` (`--quiet`); packaging moved out of `Build` into
  `ZipPackager` / `DirectoryPackager`. **Learned:** a folder target compared as a string let `.` and a
  different letter case through as "not the project" — paths are now resolved and compared without case.
- **Landed (rc.8):** build lock, atomic zip/directory output, Ctrl+C cleanup with a sweep of abandoned staging, a spinner
  with step timings (ADR-0026); multiple-choice fields as checkboxes with a chip picker and an empty marker so a
  selection can be cleared (ADR-0027). **Learned:** `proc_open` with a file descriptor lost the child's output
  after certain earlier tests in the same PHP process (cause not pinned down); the shell now does the redirect.
  The packager tests also run clean on Linux (WSL), including a real SIGINT.
- **Noticed, not fixed:** the `media` field renders `data-wptoolkit-media` for an admin script that does not exist
  yet, so its "Choose media" button does nothing.
