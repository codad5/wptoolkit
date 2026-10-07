# Phase 1 — Foundation and the coexistence proof

**Effort:** M (~1.5 active days) · **Depends on:** Phase 0 · **Unblocks:** every module

---

## Goal

**Boot a plugin on the new kernel, prove that two plugins bundling two different versions of
WPToolkit run side by side on one site, and prove that a plugin on the wrong PHP or WordPress version
can't take the site down — all before porting a single feature.**

Coexistence is the riskiest load-bearing decision in the library
([ADR-0005](../adr/0005-multiple-copies-coexist-via-scoping.md)). It's cheap to get right now and
ruinous to retrofit once every module has invented its own hook names and option keys.

> The kernel in this phase does almost nothing: it boots, registers hooks, loads translations, and
> tears down cleanly. That's the point.

---

## Scope

### 1.1 The strangler layout

- [x] Move 0.x code to `legacy/` (namespaces unchanged: `Codad5\WPToolkit\Utils\*`, `\DB\*`,
      `\Registry`) so the example plugin keeps working while modules are ported
- [x] New code under `src/` in the layered namespaces (`Foundation`, `Contracts`, `Adapters`, `Http`,
      `Data`, `View`, `Admin`, `Assets`, `Support`) — see
      [02-system-architecture.md](../architecture/02-system-architecture.md)
- [x] `composer.json` on `next`: `"php": ">=8.1"`, autoload both `src/` and `legacy/`
- [x] PHPStan level 8 on `src/` (no baseline); `legacy/` keeps Phase 0's baseline

### 1.1b Usage inventory of the real consumers

- [x] Catalogue every 0.x API used by `pau`, `silverbird-fusionintel` and `nile-distribution`
      (class, method, options passed, data written) into
      [docs/reference/0x-usage-inventory.md](../reference/0x-usage-inventory.md) — this decides
      what 1.0 must cover and feeds the migration guide
- [ ] Capture each project's stored data shapes (meta keys, option keys) as fixtures for the
      data-compatibility suite ([ADR-0016](../adr/0016-1-0-reads-0x-data-unchanged.md))

### 1.2 The kernel ([ADR-0007](../adr/0007-own-container-no-static-registry.md))

- [x] `Contracts\Container` + `Foundation\Container`: bind, singleton, factory, autowiring by
      constructor type, circular-dependency error naming the cycle
- [x] `Foundation\ServiceProvider` (`register()` then `boot()`), deferred providers
- [x] `Foundation\Application::create(string $pluginFile, array $config)` → `->providers([...])` →
      `->boot()`; no statics
- [x] `Foundation\HookRegistrar`: add/remove actions and filters, remembers everything it added,
      `removeAll()` on deactivation (replaces `add_tracked_action`)
- [x] `Foundation\Config`: readonly value object (closes the `Config::$slug` TODO); no `__get`/`__set`
- [ ] Lifecycle: activation, deactivation, uninstall hooks wired through the application
- [x] Boot timing: `register()` runs when the plugin loads; the application loads the
      **consumer's** text domain (from `Text Domain` / `Domain Path`) and then runs every
      provider's `boot()` on `init` — so consumer code that calls `__()` while building pages or
      routes can't trigger WordPress 6.7's "translation loading triggered too early" notice
      (the pau bug in the old `BUGFIX_INSTRUCTIONS.md`)

### 1.3 Identity — the consumer's names ([ADR-0006](../adr/0006-library-text-domain-and-consumer-prefixes.md))

- [ ] `Foundation\Identity`: derives every WP-global name from the slug — `hook()`, `ajaxAction()`,
      `restNamespace()`, `optionKey()`, `transientKey()`, `table()`, `cronHook()`, `handle()`,
      `jsGlobal()`, `nonceAction()`
- [ ] Unit tests: two identities never produce the same key; keys respect WordPress length limits
      (option names 191, transient keys 172, hook names unlimited but sane)

### 1.4 Localization foundation ([04-localization.md](../architecture/04-localization.md))

- [ ] `Contracts\Translator` + `Adapters\I18n\WpTranslator` + `ArrayTranslator` (tests)
- [ ] `languages/` directory; library strings use text domain `wptoolkit`
- [ ] Library translations loaded on `init` via `load_textdomain()` from the package's own path,
      overridable by a consumer-prefixed filter
- [ ] `composer i18n` → `wp i18n make-pot`; CI fails if `languages/wptoolkit.pot` is stale

### 1.5 Coexistence ([03-multi-version-coexistence.md](../architecture/03-multi-version-coexistence.md))

- [ ] `Application::VERSION` constant, bumped by the release workflow
- [ ] The coexistence ledger: each copy records `{version, path, namespace}` at load
- [ ] `requires_toolkit` constraint in `Application::create()`; on mismatch → admin notice naming
      both plugins and the fix, **refuse to boot** (no fatal)
- [ ] Strauss configuration documented and used by both fixture plugins
- [ ] **Composer is optional** ([ADR-0014](../adr/0014-composer-is-optional.md)):
      `bootstrap/autoload.php` (~50-line PSR-4 loader, no globals) replaces the 654-line 0.x
      `Autoloader`; `bin/scope.php MyPlugin` rewrites the namespace with only the PHP CLI
- [ ] Guard `autoload: 'auto'`: uses the plugin's `vendor/autoload.php` when present, else the
      standalone loader; standalone loader is idempotent per copy (tests for both paths)
- [ ] Coexistence fixtures in **both** flavours: two Strauss-scoped Composer copies, and two
      `bin/scope.php`-scoped standalone copies
- [ ] Fixtures: `tests/Fixtures/plugin-alpha` (scoped, version A) and `plugin-beta` (scoped,
      version B); `plugin-gamma` + `plugin-delta` (unscoped, different versions) for the conflict path

### 1.6 Safe boot — the guard ([ADR-0013](../adr/0013-plugins-boot-through-a-syntax-safe-guard.md))

- [ ] `bootstrap/guard.php` in PHP 5.6 syntax; `return`s a closure; defines no global names
- [ ] Checks: PHP version, WordPress version, required extensions, optional required plugins
- [ ] On a failed check: load nothing; plugin stays active but inert; admin notice (plugin name,
      needs vs has, deactivate link); WP-CLI warning; activation refused with a clear message
- [ ] Boot callback wrapped in `catch (Throwable)`: `ParseError`, Composer platform-check failures and
      boot exceptions make the plugin inert + logged + noticed, never a white screen
- [ ] `HookRegistrar` containment mode: callbacks wrapped, exceptions logged; on in production, off
      in development
- [ ] Example and fixture plugins' main files use the guard
- [ ] CI: `php -l` on the guard and those main files under the oldest PHP image available; PHPCS
      PHPCompatibility `testVersion 5.6-` scoped to those files

### 1.7 Test infrastructure

- [ ] `.wp-env.json` (WordPress + the fixtures); `composer test:integration` via wp-phpunit
- [ ] CI `integration` job: MySQL service; matrix PHP {8.1, 8.4} × WP {6.4, latest}
- [ ] Playwright skeleton (`tests/E2E/`) running against wp-env; CI job on label `e2e` + nightly

---

## Definition of Done

**The boot test.** The Todo example plugin boots on `Application`; deactivating it leaves zero of its
hooks registered (asserted by `HookRegistrar` and by inspecting `$wp_filter`).

**The coexistence test** — on one wp-env site, demonstrated end to end:

1. `plugin-alpha` and `plugin-beta` (scoped, different versions) are both active.
2. Both admin pages load; each reports its own `Application::VERSION`.
3. No PHP warnings, notices or deprecations in the log.
4. No shared WP-global key: dumping options, transients, hooks, REST namespaces and script handles
   shows every key prefixed by its own plugin's slug.
5. With `plugin-gamma` + `plugin-delta` (unscoped, different versions) active instead, the site
   **does not fatal**: the plugin whose constraint fails shows an admin notice and stays inert.

**The safe-boot test** — each demonstrated on wp-env, never assumed:

- [ ] A fixture plugin declaring `php: 99.0` is active: the site's front end and wp-admin both load,
      the plugin shows its notice, and none of its code ran.
- [ ] A fixture plugin whose `src/boot.php` contains a syntax error: same outcome, plus a log entry
      naming the file and line.
- [ ] A fixture plugin whose boot throws an exception: same outcome.
- [ ] Trying to activate the `php: 99.0` fixture from the Plugins screen is refused with a readable
      message.

**The i18n test.** With the site in `fr_FR` and a test `.mo` in place, a library string renders in
French, and translations are not loaded before `init` (no WP 6.7 notice).

**The gates.** Lint, PHPStan level 8 on `src/`, unit, integration (matrix), i18n — all green.

## Deliberately not in this phase

Porting any 0.x module. Cache, HTTP, data — all later phases. The Abilities integration (Phase 6).

## Risks

| Risk                                                         | Mitigation                                                                   |
| ------------------------------------------------------------ | ---------------------------------------------------------------------------- |
| Strauss misses dynamic class strings (`'Codad5\\WPToolkit\\…'`) | Ban class-name strings in `src/` (use `::class`); PHPCS sniff                  |
| Autowiring by reflection is slow on every request            | Resolve lazily; cache constructor metadata per request; measure in Phase 7   |
| Two copies load the same `.mo` for `wptoolkit`              | Accepted: WordPress merges them; identical source strings translate the same |
