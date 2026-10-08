# Phase 6 — Developer experience, docs and AI-readiness

**Effort:** M (~0.75 active day) · **Depends on:** Phase 5 · **Unblocks:** adoption

---

## Goal

**A developer — or an AI coding agent — can go from nothing to a correct, secure, translatable
plugin quickly, because the safe path is the default path and it's written down where tools look.**

---

## Scope

### 6.1 WP-CLI

- [x] `wp {slug} make:entity|controller|provider|field|migration` scaffolding (tested templates: every
      stub generates PHP that passes `php -l`; namespace from the plugin's composer.json; no overwrite
      without `--force`)
- [x] `wp {slug} migrate`, `migrate:status`, `migrate:rollback` (`migrate` and `migrate:rollback` take `--dry-run`)
- [x] `wp {slug} routes:list` (REST, Ajax and public pages, with each access rule), `hooks:list`,
      `toolkit:info` (version, path, scoped or not, other copies seen in the coexistence ledger) — the
      E2E coexistence suite runs it on both plugins

### 6.2 Documentation

- [ ] API reference generated from docblocks (phpDocumentor) in CI — **deferred to 1.1**: the guides
      and `llms.txt` cover the public API; phpDocumentor adds a large toolchain for little gain now
- [x] Guides: [getting started](../guides/getting-started.md), [routes and security](../guides/routes-and-security.md),
      [data](../guides/data.md), [admin and front end](../guides/admin-and-front-end.md) (views,
      assets and localization), [scoping and coexistence](../guides/scoping-and-coexistence.md).
      A dedicated "testing your plugin" guide is deferred to 1.1 (`ArrayRepository` and the contract
      tests are mentioned in the data guide)
- [x] Retire the hand-maintained `API.md` (5,075 lines), `BEST-PRATICE.MD`, `EXAMPLES.md` — fold
      what's still true into the guides; move the originals to `docs/archive/0x/`
- [x] README rewritten: honest feature list, real badges, PHP/WP requirements

### 6.3 AI-readiness

- [x] `llms.txt` at the repo root: what the library is, twelve rules for code that uses it, the public API in one page, links to guides
- [ ] A consumer-facing `AGENTS.md` template that `make:plugin` drops into new plugins — **deferred
      to 1.1** with `make:plugin`; the same rules are in `llms.txt` today
- [ ] Optional `Integrations\Abilities`: expose chosen routes as WordPress Abilities (for AI agents
      through the WordPress MCP adapter), reusing the route's own access rules.
      **First verify the Abilities API status in the WordPress versions we support.** — **deferred to
      1.1**, as the risk table allows: it is not part of WordPress 6.4–6.6, which 1.x supports

### 6.4 Examples

- [x] `examples/todo` rebuilt idiomatically (entity, settings, columns, public page, API, Arabic
      translation; booted by a unit test and driven end to end). `examples/scoped-plugin` with Strauss:
      deferred — the E2E coexistence fixtures already ship scoped copies built with `bin/scope.php`
- [x] `export-ignore`d from the dist package

### 6.5 Migration guide

- [x] `docs/guides/migrating-from-0x.md`: class-by-class from
      [07-migration-from-0x.md](../architecture/07-migration-from-0x.md), with before/after code
      **taken from the real consumers** (`pau`, `silverbird-fusionintel`, `nile-distribution`),
      using the Phase 1 usage inventory
- [x] A "watch out for" section from those ports: routes that relied on the open REST default
      (now need `->public()`), Ajax actions relying on `'public' => true` by default, `__()` calls
      at `plugins_loaded`, `Registry::get()` calls, git-cloned copies of the library (→ scoped
      standalone zip), `dev-main` constraints (→ `^1.0`)
- [x] States plainly: **no data migration needed** (ADR-0016)
- [ ] Rector rules for the mechanical renames — **not feasible**: 0.x → 1.0 is a change of design
      (Registry → injection, Model → entity + repository), not renames a rule can apply safely

---

## Definition of Done

- A new developer follows "getting started" on a clean machine and has a scoped plugin with one
  entity, one route and one translated string running on wp-env — timed, and under 15 minutes.
- An AI agent given only `llms.txt` and the guides generates a route that passes the security
  regression suite on the first try (try it with two different agents; record results in the
  work log).
- `wp {slug} toolkit:info` on the coexistence site lists both copies correctly.

## Status of the Definition of Done

- `toolkit:info` lists both copies: **done**, asserted in `tests/E2E/coexistence.spec.ts`.
- The 15-minute timed run and the two-agent `llms.txt` trial need a person and fresh machines/agents;
  they are **waiting on the maintainer** (record results in the work log).

## Deliberately not in this phase

A docs website with its own domain (GitHub Pages from `docs/` is enough for 1.0). Video tutorials.

## Risks

| Risk                                       | Mitigation                                        |
| ------------------------------------------ | ------------------------------------------------- |
| Abilities API differs from what we assume  | It's optional; ship it in 1.1 if it isn't stable  |
