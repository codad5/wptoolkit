# Phase 6 — Developer experience, docs and AI-readiness

**Effort:** M (~0.75 active day) · **Depends on:** Phase 5 · **Unblocks:** adoption

---

## Goal

**A developer — or an AI coding agent — can go from nothing to a correct, secure, translatable
plugin quickly, because the safe path is the default path and it's written down where tools look.**

---

## Scope

### 6.1 WP-CLI

- [ ] `wp {slug} make:entity|controller|provider|field|migration` scaffolding (tested templates)
- [ ] `wp {slug} migrate`, `migrate:status`, `migrate:rollback` (all with `--dry-run`)
- [ ] `wp {slug} routes:list`, `hooks:list`, `toolkit:info` (version, path, scoped or not, other
      copies seen in the coexistence ledger)

### 6.2 Documentation

- [ ] API reference generated from docblocks (phpDocumentor) in CI
- [ ] Guides: getting started, entities and fields, routes and security, localization, coexistence
      and scoping (Strauss walkthrough), testing your plugin
- [ ] Retire the hand-maintained `API.md` (5,075 lines), `BEST-PRATICE.MD`, `EXAMPLES.md` — fold
      what's still true into the guides; move the originals to `docs/archive/`
- [ ] README rewritten: honest feature list, real badges, PHP/WP requirements

### 6.3 AI-readiness

- [ ] `llms.txt` at the repo root: what the library is, the public API in one page, links to guides
- [ ] A consumer-facing `AGENTS.md` template that `make:plugin` drops into new plugins, teaching an
      agent the ten rules for code that *uses* WPToolkit
- [ ] Optional `Integrations\Abilities`: expose chosen routes as WordPress Abilities (for AI agents
      through the WordPress MCP adapter), reusing the route's own access rules.
      **First verify the Abilities API status in the WordPress versions we support.**

### 6.4 Examples

- [ ] `examples/todo-plugin` rebuilt idiomatically; `examples/scoped-plugin` showing Strauss end to end
- [ ] Both `export-ignore`d from the dist package

### 6.5 Migration guide

- [ ] `docs/guides/migrating-from-0x.md`: class-by-class from
      [07-migration-from-0x.md](../architecture/07-migration-from-0x.md), with before/after code
      **taken from the real consumers** (`pau`, `silverbird-fusionintel`, `nile-distribution`),
      using the Phase 1 usage inventory
- [ ] A "watch out for" section from those ports: routes that relied on the open REST default
      (now need `->public()`), Ajax actions relying on `'public' => true` by default, `__()` calls
      at `plugins_loaded`, `Registry::get()` calls, git-cloned copies of the library (→ scoped
      standalone zip), `dev-main` constraints (→ `^1.0`)
- [ ] States plainly: **no data migration needed** (ADR-0016)
- [ ] Rector rules for the mechanical renames, where feasible

---

## Definition of Done

- A new developer follows "getting started" on a clean machine and has a scoped plugin with one
  entity, one route and one translated string running on wp-env — timed, and under 15 minutes.
- An AI agent given only `llms.txt` and the guides generates a route that passes the security
  regression suite on the first try (try it with two different agents; record results in the
  work log).
- `wp {slug} toolkit:info` on the coexistence site lists both copies correctly.

## Deliberately not in this phase

A docs website with its own domain (GitHub Pages from `docs/` is enough for 1.0). Video tutorials.

## Risks

| Risk                                       | Mitigation                                        |
| ------------------------------------------ | ------------------------------------------------- |
| Abilities API differs from what we assume  | It's optional; ship it in 1.1 if it isn't stable  |
