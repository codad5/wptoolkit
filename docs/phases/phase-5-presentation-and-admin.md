# Phase 5 — Presentation and admin

**Effort:** M (~1 active day) · **Depends on:** Phase 4 · **Unblocks:** deleting `legacy/`

---

## Goal

**Views, assets, admin pages, settings and notices on the new core — escaped by default, enqueued at
the right time, readable right-to-left — and then `legacy/` is deleted.**

---

## Scope

### 5.1 Views — ports `ViewLoader` + `ViewHelper`

- [x] `Contracts\View\Renderer` + `PhpTemplateRenderer` (a Twig adapter is a separate package, later);
      templates run in a sealed scope and failures throw instead of 0.x's silent `false`
- [x] `View\TemplateLocator`: theme override paths kept (`{theme}/{slug}/…`), then plugin, then
      `{dir}/index.php`; `..` refused and matches must resolve inside their directory. No consumer used
      0.x `ViewLoader`, so there are no old override paths to keep as a fallback
- [x] `View\Escaper` passed to templates as `$e` (`$e->html()`, `$e->attr()`, `$e->url()`, …) — its
      methods print, so templates never need `echo`; `TemplateEscapingSniffTest` proves a raw echo fails PHPCS
- [x] Sections/partials kept from `ViewHelper` (`$view->layout()`, `start()`/`stop()`, `section()`, `insert()`)

### 5.2 Assets — ports `EnqueueManager` (fixes C4 by design)

- [x] `Assets\AssetManager`: registration only happens on `wp_enqueue_scripts`,
      `admin_enqueue_scripts`, `login_enqueue_scripts`; calling early queues instead of erroring
- [x] Reads `@wordpress/scripts` `*.asset.php` manifests (dependencies + version)
- [x] Handles via `Identity::handle()`; `wp_set_script_translations` for every script that depends on `wp-i18n`
- [x] Localized data goes to `window.wptoolkit[slug]` (`Identity::jsAccessor()`), written with a
      merge (`window.wptoolkit = window.wptoolkit || {}`) so no plugin can wipe another's entry;
      each entry carries `toolkitVersion` — there is no single top-level version
- [x] Lock the namespace itself (`Object.defineProperty(window, 'wptoolkit', { value: …, writable:
      false, configurable: false })`) so no script can replace it; entries stay writable by
      their own plugin. Proven by running the generated script in Node (`AssetManagerTest`): assigning
      `window.wptoolkit = {}` doesn't wipe other entries. The JS client is wired the same way
      (`AssetManager::client($router)` → `window.wptoolkit[slug].api`, routes from `Router::clientConfig()`)
- [x] RTL: `wp_style_add_data($handle, 'rtl', 'replace')` for styles that ship an `-rtl.css`

### 5.3 Admin pages and frontend routes — splits `Page` (1,831 lines)

- [x] `Admin\Page` builders (`top`, `under`, `hidden`, `postTypeList`) + `Admin\Pages` (menu, capability
      required and re-checked before rendering, help tabs, `url()` = 0.x `getAdminUrl()`). Slugs are kept
      verbatim so 0.x admin URLs keep working. Screen options: not used by any consumer — deferred
- [ ] ~~Frontend virtual pages~~ — **not ported**: no consumer (pau, silverbird, nile) uses 0.x
      `addFrontendPage()`, and the rewrite-rule machinery is a liability to carry without a user.
      Recorded under "Deliberately not in this phase"; add it in 1.x if someone needs it

### 5.4 Settings — ports `Settings`

- [x] On the Settings API, built from the Phase 4 field system; per-field sanitize/validate
      (`Admin\Settings\Settings` + `SettingsForm`; an invalid value keeps the old one and shows why)
- [x] Storage with `Identity::optionKey()` — one option per setting, exactly 0.x's keys and formats
      (`OptionsRepository` keeps a whole collection in one option, which would have broken ADR-0016)
- [x] **Sensitive fields** ([ADR-0021](../adr/0021-sensitive-settings-are-read-only-by-name.md)): readable only by
      name; omitted from `all()`, JSON, exports and REST/Ajax; password input that never echoes
      the stored value (blank keeps it); keys registered for log redaction; refused in JS
      localization; optional sodium encryption at rest
- [x] Regression test reproducing pau's `/settings` route: the API key is absent from the response
- [x] Settings upcasters (moved from Phase 4.3b): `Settings::upcast($name, fn ($old) => …)`

### 5.5 Notices — ports `Notification`

- [x] `Admin\Notices` (one-time flash, persistent, dismissible per user), stored in user meta, not by
      scanning the options table; `role="status"` / `role="alert"`; dismissal via a nonce-checked,
      logged-in-only Ajax action

### 5.6 Delete `legacy/`

- [ ] Every row in [07-migration-from-0x.md](../architecture/07-migration-from-0x.md) is "done"
- [ ] `legacy/` and its PHPStan baseline deleted; `Autoloader` and `Registry` gone

---

## Definition of Done

- A template that echoes user input without the escaper fails a PHPCS sniff in CI.
- With `WP_DEBUG` on, the Todo example loads admin and frontend with **zero** `_doing_it_wrong`
  notices (C4 can't happen by construction).
- **E2E in `en_US` and `ar` (RTL)**: admin page, settings save, metabox save, frontend list — layout
  mirrored, strings translated by the test `.mo`.
- Keyboard-only E2E: every form control is reachable and labelled.
- `legacy/` does not exist.

## Deliberately not in this phase

A block-editor settings UI. A React admin framework. Twig/Blade adapters (separate packages).
Frontend virtual pages from 0.x `Page::addFrontendPage()` (no consumer uses them), appearance-menu
groups and dashboard widgets from 0.x `Page` (likewise unused).

## Risks

| Risk                                                    | Mitigation                                             |
| ------------------------------------------------------- | ------------------------------------------------------ |
| Theme-override paths change and break existing themes   | Keep 0.x lookup paths as a fallback for one minor version |
