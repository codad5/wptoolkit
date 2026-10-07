# ADR-0006 — Library strings use `wptoolkit`; everything global uses the consumer's slug

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** [04-localization.md](../architecture/04-localization.md), [03-multi-version-coexistence.md](../architecture/03-multi-version-coexistence.md), `Foundation\Identity`

## Context

Two different "names" are easy to conflate:

1. **The text domain of strings the library itself owns** — "Invalid nonce", "This field is
   required", a notice's "Dismiss". WordPress's tooling (`wp i18n make-pot`) extracts strings by a
   **literal** domain argument, so a library string can't take a domain from a variable.
2. **Every key the library writes into WordPress on the consumer's behalf** — hooks, options,
   tables, handles. Those are the consumer's data.

0.x mixes them: it translates library strings with the *consumer's* text domain from config (which
breaks extraction), and uses some bare `wptoolkit_` keys (which breaks coexistence, ADR-0005).

## Decision

- **Library-owned strings** use the literal text domain **`wptoolkit`**, ship as
  `languages/wptoolkit.pot` (+ `.mo` / `.json` per locale), and are loaded on `init` from the
  package's own path. A consumer can override the path with a filter.
- **Consumer strings** (labels they pass in: field labels, menu titles) are already translated by
  the consumer with their own domain before they reach us — we never re-translate them.
- **Everything in WordPress's global namespace** is derived from the consumer's slug by
  `Foundation\Identity`:

| Kind            | Pattern (slug `my-plugin`)                |
| --------------- | ----------------------------------------- |
| Hooks           | `my-plugin/http/before_dispatch`          |
| Ajax actions    | `my_plugin_books_search`                  |
| REST namespace  | `my-plugin/v1`                            |
| Options         | `my_plugin_settings`                      |
| Transients      | `my_plugin_c_<hash>`                      |
| Tables          | `{$wpdb->prefix}my_plugin_books`          |
| Cron hooks      | `my_plugin_cleanup`                       |
| Script handles  | `my-plugin-toolkit-api`                   |
| JS global       | `window.myPluginToolkit`                  |
| Nonce actions   | `my-plugin:books.search`                  |

The one exception is the coexistence ledger and a single diagnostic action, `wptoolkit/loaded`.

## Options considered

### Option A — Library strings use the consumer's domain (0.x)

**Lost:** `make-pot` can't extract them; every consumer would have to re-translate our strings.

### Option B — Everything under `wptoolkit_` / `wptoolkit/`

**Lost:** two copies collide (ADR-0005); one plugin's hooks fire another plugin's listeners.

### Option C — Split by ownership (chosen)

Our words in our domain; their data under their name.

## Consequences

**We accept:** two scoped copies of different versions both load `wptoolkit` translations;
WordPress merges them, and identical source strings translate identically. A string whose wording
changed between versions may show the other version's translation — a cosmetic cost.

**We gain:** extractable, shareable translations; zero WordPress-level collisions.

**This constrains:** CLAUDE.md rules 3 and 6; the `75-i18n-and-naming` rule file; a PHPCS sniff
forbidding string-literal hook names, option keys and handles outside `Identity`.

## Revisit when

Translations are contributed via translate.wordpress.org for a plugin that bundles us and the
merge behaviour causes real complaints.
