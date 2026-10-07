# ADR-0016 — 1.0 reads and writes 0.x data unchanged; only the PHP API breaks

- **Status:** Accepted
- **Date:** 2026-10-07
- **Deciders:** codad5
- **Affects:** `Foundation\Identity`, `Data\MetaBox`, `Data\Field`, `Admin\Settings`, repositories, [07-migration-from-0x.md](../architecture/07-migration-from-0x.md)

## Context

1.0 is a major version, so its **PHP API** may change. But sites running 0.x already have data in
their databases, written in 0.x's formats. A plugin upgraded to 1.0 that can't see its existing
meta and settings has, from the site owner's view, lost them.

What 0.x stores (verified in the source, 2026-10-07):

| Data                | Where          | Key / format                                                                                    |
| ------------------- | -------------- | ----------------------------------------------------------------------------------------------- |
| MetaBox field value | `wp_postmeta`  | key **`{metabox_id}_{post_type}_{field_id}`** (or a custom prefix from `set_prefix()`); single value per key, arrays serialized by WordPress |
| Multiple media      | `wp_postmeta`  | same key, **one row per attachment ID** (`add_post_meta` non-unique)                            |
| Settings            | `wp_options`   | **`{option_prefix}{sanitize_key($key)}`**, default prefix `{app_slug}_`, one option per setting |
| Post types, taxonomies | WordPress   | names from the consumer's `POST_TYPE` constants and taxonomy config                             |
| Cache, notifications, rate limits | transients | ephemeral — safe to drop                                                         |

Note the slug in option keys is the raw app slug, which can contain hyphens
(`pau-alumni-manager_api_key`).

## Decision

1. **Persisted keys and value formats are part of the public contract**, frozen exactly as above.
   1.0 reads and writes them with no migration step.
2. `Foundation\Identity` produces the 0.x key shapes for meta and options by default
   (`metaKey($box, $postType, $field)`, `optionKey($key)` = `{slug}_{key}` with the slug unchanged).
   New kinds of data (tables, cron hooks, …) follow ADR-0006.
3. Multiple-value fields keep the one-row-per-value storage.
4. A consumer *may* opt into a different scheme later only through an explicit, reversible
   migration command (`wp {slug} migrate:keys --dry-run`) — never implicitly.
5. **Data-compatibility tests:** a fixture database written by 0.x (captured from the real
   consumers' shapes) is loaded in integration tests, and 1.0 must read every value identically.

## Options considered

### Option A — New, cleaner key scheme with an automatic migration on upgrade

**Lost:** an automatic migration on a live site is a data-loss risk with no upside for the site
owner; downgrading becomes impossible.

### Option B — Freeze 0.x's storage formats (chosen)

**Pros:** upgrades are a code change only; downgrade still works. **Cons:** 1.0 inherits 0.x's
key shapes, including hyphens in option names.

## Consequences

**We accept:** slightly inconsistent key shapes in 1.0, documented here.

**We gain:** zero-migration upgrades for `pau`, `silverbird-fusionintel` and `nile-distribution`.

**This constrains:** Phase 4 (MetaBox, fields, repositories) and Phase 5 (Settings) must pass the
data-compatibility suite. Changing a stored key or format needs a superseding ADR.

## Revisit when

A key shape causes a real bug (e.g. collisions between two metaboxes) — then add an opt-in
migration, not a default change.
