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

- [ ] `Assets\AssetManager`: registration only happens on `wp_enqueue_scripts`,
      `admin_enqueue_scripts`, `login_enqueue_scripts`; calling early queues instead of erroring
- [ ] Reads `@wordpress/scripts` `*.asset.php` manifests (dependencies + version)
- [ ] Handles via `Identity::handle()`; `wp_set_script_translations` for every script with strings
- [ ] Localized data goes to `window.wptoolkit[slug]` (`Identity::jsAccessor()`), written with a
      merge (`window.wptoolkit = window.wptoolkit || {}`) so no plugin can wipe another's entry;
      each entry carries `toolkitVersion` — there is no single top-level version
- [ ] Lock the namespace itself (`Object.defineProperty(window, 'wptoolkit', { value: …, writable:
      false, configurable: false })`) so no script can replace it; entries stay writable by
      their own plugin. E2E: a script assigning `window.wptoolkit = {}` doesn't wipe other entries
- [ ] RTL: `wp_style_add_data($handle, 'rtl', 'replace')` for styles that ship an `-rtl.css`

### 5.3 Admin pages and frontend routes — splits `Page` (1,831 lines)

- [ ] `Admin\Page` / `Admin\SubPage` builders (menu, capability required, screen options, help tabs)
- [ ] Frontend virtual pages move to the router (Phase 3) + a template, not a separate system

### 5.4 Settings — ports `Settings`

- [ ] On the Settings API, built from the Phase 4 field system; per-field sanitize/validate
- [ ] Storage through `OptionsRepository` with `Identity::optionKey()`
- [ ] **Sensitive fields** ([ADR-0021](../adr/0021-sensitive-settings-are-read-only-by-name.md)): readable only by
      name; omitted from `all()`, JSON, exports and REST/Ajax; password input that never echoes
      the stored value (blank keeps it); keys registered for log redaction; refused in JS
      localization; optional sodium encryption at rest
- [ ] Regression test reproducing pau's `/settings` route: the API key is absent from the response

### 5.5 Notices — ports `Notification`

- [ ] `Admin\Notice` (one-time, persistent, dismissible per user), stored per user, not by scanning
      the options table; `role="status"` / `role="alert"`

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

## Risks

| Risk                                                    | Mitigation                                             |
| ------------------------------------------------------- | ------------------------------------------------------ |
| Theme-override paths change and break existing themes   | Keep 0.x lookup paths as a fallback for one minor version |
