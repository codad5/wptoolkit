# Phase 4 — Data layer: fields, entities, repositories

**Effort:** L (~1.5–2 active days) · **Depends on:** Phases 2 and 3 · **Unblocks:** Phase 5

---

## Goal

**Replace the 2,655-line `Model` and 1,272-line `MetaBox` with small parts that each do one job: a
field system, entities, repositories with swappable storage, a query builder and a search that is
actually correct.**

This is the critical path and the biggest phase. It's where most consumer code lives.
([ADR-0009](../adr/0009-entity-and-repository-not-active-record.md))

---

## Scope

### 4.1 Field system (Factory + Strategy)

- [ ] `Data\Field\Field` (immutable definition) with `sanitize()`, `validate()`, `render()` delegated
      to its type
- [ ] `Data\Field\FieldFactory`: `text`, `textarea`, `number`, `email`, `url`, `select`, `checkbox`,
      `radio`, `date`, `color`, `media`, `wysiwyg`, `relation`, `repeater`, `group`
- [ ] **Third-party field types register with the factory** — no edits to core (replaces the
      `match ($field['type'])` blocks)
- [ ] Every field: `<label for>`, `aria-describedby` for its error, translatable labels

### 4.2 MetaBox, rebuilt on fields

- [ ] `Data\MetaBox` built from fields; closes the `MetaBox::$id` read-only TODO
- [ ] On every save and every Ajax read: nonce, `edit_post` capability, post type matches the box's
      screens, autosave/revision skipped
- [ ] Quick edit support kept

### 4.3 Entities and repositories (Adapter)

- [ ] `Data\Entity` (typed attributes, dirty tracking, no persistence logic) and `#[PostType]`,
      `#[Taxonomy]` attributes for registration
- [ ] `Contracts\Data\Repository` (find, findMany, query, save, delete, count)
- [ ] Adapters: `PostTypeRepository` (CPT + meta + terms), `OptionsRepository`,
      `CustomTableRepository` (`$wpdb` + `prepare()` only, with a small migration runner)
- [ ] `RepositoryFactory` picks the adapter an entity declares
- [ ] Caching through `CacheStore`, invalidated on save/delete

### 4.3b Migrations ([ADR-0018](../adr/0018-versioned-data-migrations-expand-contract.md))

- [ ] `Data\Migrations\Migration` (`id()`, `up()`, optional `down()`, optional batching)
- [ ] `Migrator`: applied ids in `{slug}_migrations`; lock with expiry; idempotent runs; stops and
      logs on failure; admin notice
- [ ] Batched migrations via WP-Cron with recorded progress and resume
- [ ] Runs on activation and on `admin_init` when the latest id isn't recorded
- [ ] Settings upcasters (old stored shape → current shape on read)
- [ ] Integration tests: rename a meta key on 1,000 posts in batches; interrupted run resumes;
      concurrent runs blocked by the lock; rollback restores

### 4.4 Query builder

- [ ] `Data\Query\QueryBuilder`: where / whereMeta / whereTerm / orderBy / paginate, compiled to
      `WP_Query` args for post types and to prepared SQL for custom tables
- [ ] Hard cap on page size; no `posts_per_page => -1` from user input

### 4.5 Search — fixes C1

- [ ] `Data\Search\Search` with OR semantics across title, content, chosen meta keys and terms
      (implemented with `posts_search` / `posts_where` + `$wpdb->prepare`, or ID-union of sub-queries)
- [ ] Respects post status and the entity's visibility policy; meta in results only via an explicit
      allow-list
- [ ] Relevance scorers are strategies (`TitleScorer`, `MetaScorer`, …)

### 4.6 Admin columns

- [ ] `Admin\Columns` extracted from `Model`: declared per entity, sortable, quick-edit aware

### 4.7 Security acceptance scenarios (carried over from 0.x)

- [ ] `test_anonymous_user_cannot_read_metabox_data` (S2)
- [ ] `test_metabox_data_requires_edit_post_and_matching_post_type` (S2)
- [ ] `test_non_public_post_type_is_not_searchable_by_logged_out_users` (S1)
- [ ] `test_public_search_returns_only_published_posts` (S1 — admin-ajax runs with `is_admin()` true)
- [ ] `test_public_search_never_returns_or_searches_meta` (S1)
- [ ] `test_search_page_size_is_capped` (S1)

### 4.8 Data compatibility ([ADR-0016](../adr/0016-1-0-reads-0x-data-unchanged.md))

- [ ] Fixture database with 0.x-written meta (single, serialized array, multiple media rows,
      custom prefix) and options (hyphenated slug)
- [ ] 1.0 `MetaBox`/`PostTypeRepository` and `Settings` read every fixture value identically, and
      writes produce the same keys and formats
- [ ] Round trip: 1.0 writes → 0.x reads (downgrade stays possible)

### 4.9 Port and delete

- [ ] Todo example on entities + repositories; port `pau-alumni-manager`'s model shapes as fixtures
- [ ] Delete `legacy/DB/Model.php` and `legacy/DB/MetaBox.php`; fill in the migration map

---

## Definition of Done

- Search "foo" across title + meta returns posts matching **either** (C1 regression test), never a
  draft or private post to a user who can't read it, and never a meta key outside the allow-list.
- A custom field type from a test plugin registers with `FieldFactory` and renders, sanitizes and
  validates with **no change to `src/`**.
- The same `Book` entity runs against `PostTypeRepository` and `CustomTableRepository` with one
  config change; the shared repository contract tests pass for both.
- An anonymous request cannot read any metabox data (S2 regression, new layer).
- `Model.php` and `MetaBox.php` are gone from `legacy/`.

## Deliberately not in this phase

Relationships beyond `relation` fields (no ORM). Schema diffing for custom tables (migrations are
hand-written). Block editor (Gutenberg) sidebar panels (1.1).

## Risks

| Risk                                                     | Mitigation                                                           |
| -------------------------------------------------------- | -------------------------------------------------------------------- |
| OR-search across meta is slow on large sites             | Allow-listed keys only; an index advice note; benchmark with 50k posts |
| Consumers relied on `Model`'s singleton accessors        | Migration guide + a thin `legacy` shim during 0.x → 1.0 if dogfooding shows the need |
